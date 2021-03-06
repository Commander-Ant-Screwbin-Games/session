<?php declare(strict_types=1);
/**
 * MIT License
 * 
 * Copyright (c) 2021 Nicholas English
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace Omatamix\SessionLock;

use Omatamix\SessionLock\Exception\InvalidFingerprintException;
use Symfony\Component\OptionsResolver\OptionsResolver;
use SessionHandlerInterface;

/**
 * Securely manage and preserve session data.
 */
final class SessionManager implements SessionManagerInterface
{
    /** @var array $options The session manager options. */
    private $options = [];

    /**
     * {@inheritdoc}
     */
    public function __construct(array $options = [])
    {
        $this->setOptions($options);
    }

    /**
     * {@inheritdoc}
     */
    public function setOptions(array $options = []): SessionManagerInterface
    {
        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);
        $this->options = $resolver->resolve($options);
        return $this;
    }

     /**
     * {@inheritdoc}
     */
    public function setSessionName(string $name)
    {
        return session_name($name);
    }

    /**
     * {@inheritdoc}
     */
    public function setSaveHandler(SessionHandlerInterface $sessionHandler): void
    {
        session_set_save_handler($sessionHandler, true);
    }

    /**
     * {@inheritdoc}
     */
    public function start(): bool
    {
        $session = session_start($this->options['config']);
        if ($this->options['fingerprinting']) {
            if ($this->has('session-lock.fingerprint')) {
                if (!hash_equals($this->has('session-lock.fingerprint'), $this->getFingerprint())) {
                    $this->stop();
                    throw new InvalidFingerprintException('The fingerprint supplied is invalid.');
                }
            } else {
                $this->put('session-lock.fingerprint', $this->getFingerprint());
            }
        }
        return $session;
    }

    /**
     * {@inheritdoc}
     */
    public function stop(): bool
    {
        $_SESSION = array();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        return session_destroy();
    }

    /**
     * {@inheritdoc}
     */
    public function exists(): bool
    {
        if (php_sapi_name() !== 'cli') {
            return session_status() === PHP_SESSION_ACTIVE ? true : false;
        }
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function regenerate(bool $deleteOldSession = true): bool
    {
        return session_regenerate_id($deleteOldSession);
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key, $defaultValue = null)
    {
        return $this->has($key) ? $_SESSION[$key] : $defaultValue;
    }

    /**
     * {@inheritdoc}
     */
    public function flash(string $key, $defaultValue = null)
    {
        $value = $this->get($key, $defaultValue);
        $this->delete($key);
        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function put(string $key, $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Get the fingerprint from the current accessing user.
     *
     * @return string Returns the session fingerprint.
     */
    private function getFingerprint(): string
    {
        $remoteIp = $userAgent = '';
        if ($this->options['bind_ip_address'] && isset($_SERVER['REMOTE_ADDR'])) {
            $remoteIp = !is_null($this->options['use_ip']) ? $this->options['use_ip'] : $_SERVER['REMOTE_ADDR'];
        }
        if ($this->options['bind_user_agent'] && isset($_SERVER['HTTP_USER_AGENT'])) {
            $userAgent = $_SERVER['HTTP_USER_AGENT'];
        }
        $fingerprint = sprintf('%s|%s', $remoteIp, $userAgent);
        return hash_hmac($this->options['fingerprint_hash'], $fingerprint, $this->options['security_code']);
    }

    /**
     * Configure the session manager options.
     *
     * @param OptionsResolver The symfony options resolver.
     *
     * @return void Returns nothing.
     */
    private function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'fingerprinting'   => true,
            'fingerprint_hash' => 'sha512',
            'bind_ip_address'  => true,
            'bind_user_agent'  => true,
            'use_ip'           => '',
            'security_code'    => '',
            'config'           => [
                'use_cookies'      => true,
                'use_only_cookies' => true,
                'cookie_httponly'  => true,
                'cookie_samesite'  => 'Lax',
                'use_strict_mode'  => true,
            ],
        ]);
        $resolver->setAllowedTypes('fingerprinting', 'bool');
        $resolver->setAllowedTypes('fingerprint_hash', 'string');
        $resolver->setAllowedTypes('bind_ip_address', 'bool');
        $resolver->setAllowedTypes('bind_user_agent', 'bool');
        $resolver->setAllowedTypes('use_ip', 'string');
        $resolver->setAllowedTypes('security_code', 'string');
        $resolver->setAllowedTypes('config', 'array');
    }
}
