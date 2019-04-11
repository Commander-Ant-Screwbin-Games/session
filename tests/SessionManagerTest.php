<?php
use PHPUnit\Framework\TestCase;

class SessionManagerTest extends TestCase
{
    /**
     * @runInSeparateProcess
     */
    public function testSessions()
    {
        $sessionConfig = ['use_cookies' => \false];
        $handler = new Kooser\Session\Handler\FileSessionHandler();
        Kooser\Session\SessionManager::setSaveHandler($handler, \true);
        \session_save_path(\realpath(\dirname(__DIR__ . '/session')));
        $result = Kooser\Session\SessionManager::start($sessionConfig);
        $this->assertTrue($result);
        $id = Kooser\Session\SessionManager::id();
        $this->assertTrue(\is_string($id));
        Kooser\Session\SessionManager::gc();
        Kooser\Session\SessionManager::regenerate();
        $newId = Kooser\Session\SessionManager::id();
        $this->assertTrue(($id != $newId));
        Kooser\Session\SessionManager::abort();
        Kooser\Session\SessionManager::reset();
        Kooser\Session\SessionManager::set('key1', 'Kooser6');
        Kooser\Session\SessionManager::set('key2', 'Kooser6Session');
        $data1 = Kooser\Session\SessionManager::get('key1', null);
        $data2 = Kooser\Session\SessionManager::get('key2', null);
        $this->assertTrue(($data1 != null));
        $this->assertTrue(($data1 == 'Kooser6'));
        $this->assertTrue(($data2 != null));
        $this->assertTrue(($data2 == 'Kooser6Session'));
        $result = Kooser\Session\SessionManager::exists();
        $data3 = Kooser\Session\SessionManager::get('key3', null);
        $this->assertTrue(($data3 === null));
        $this->assertTrue($result);
    }
}
