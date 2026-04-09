<?php
/**
 * @author Davey (https://github.com/weinotes)
 */

namespace App\Services;

use Illuminate\Support\Facades\File;

class UserService
{
    private string $dataDir;
    private string $configFile;

    public function __construct()
    {
        // 数据目录配置：优先读配置文件，兼容旧路径
        $this->configFile = base_path('userdata_path.txt');
        $this->dataDir = $this->loadDataDir();
    }

    private function loadDataDir(): string
    {
        $default = base_path('userdata');
        if (file_exists($this->configFile)) {
            $path = trim(file_get_contents($this->configFile));
            if ($path && is_dir(dirname($path))) {
                return $path;
            }
        }
        // 旧路径兼容：lingxi-data 存在但新路径不存在时迁移
        $legacy = base_path('../lingxi-data'); // 保持兼容旧目录名
        if (is_dir($legacy) && !is_dir($default)) {
            File::copyDirectory($legacy, $default);
        }
        return $default;
    }

    public function getDataDir(): string
    {
        return $this->dataDir;
    }

    public function setDataDir(string $path): array
    {
        $path = rtrim($path, '/');
        $parent = dirname($path);
        if (!is_dir($parent)) {
            return ['success' => false, 'error' => '上级目录不存在：' . $parent];
        }
        if (is_dir($path)) {
            // 目标已存在，直接切换
        } else {
            if (!mkdir($path, 0755, true)) {
                return ['success' => false, 'error' => '无法创建目录：' . $path];
            }
        }
        // 迁移旧数据（如果有）
        if ($path !== $this->dataDir && is_dir($this->dataDir)) {
            $files = File::allFiles($this->dataDir);
            foreach ($files as $file) {
                $rel = $file->getRelativePathname();
                $dest = $path . '/' . $rel;
                $destDir = dirname($dest);
                if (!is_dir($destDir)) mkdir($destDir, 0755, true);
                File::copy($file->getPathname(), $dest);
            }
            // 标记旧路径（不删除，用户可确认）
        }
        file_put_contents($this->configFile, $path);
        $this->dataDir = $path;
        return ['success' => true, 'path' => $path];
    }

    public function usersDir(): string
    {
        return $this->dataDir . '/users';
    }

    public function sessionsDir(?string $username = null): string
    {
        $dir = $this->dataDir . '/sessions' . ($username ? '/' . $username : '');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }

    public function listUsers(): array
    {
        $dir = $this->usersDir();
        if (!is_dir($dir)) return [];

        $users = [];
        foreach (glob($dir . '/*.json') as $file) {
            $user = json_decode(file_get_contents($file), true);
            $user['username'] = basename($file, '.json');
            $user['file'] = $file;
            $users[] = $user;
        }
        return $users;
    }

    public function getUser(string $username): ?array
    {
        $file = $this->usersDir() . '/' . $username . '.json';
        if (!file_exists($file)) return null;
        $user = json_decode(file_get_contents($file), true);
        $user['username'] = $username;
        return $user;
    }

    public function verifyPassword(string $username, string $password): bool
    {
        $user = $this->getUser($username);
        if (!$user) return false;
        if (empty($user['password'])) return true; // no password set
        return md5($password) === $user['password'];
    }

    public function createUser(string $username, string $password = ''): array
    {
        $user = [
            'username' => $username,
            'password' => $password ? md5($password) : '',
            'created_at' => now()->toISOString(),
            'sessions' => [],
        ];
        $file = $this->usersDir() . '/' . $username . '.json';
        File::put($file, json_encode($user, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $user;
    }

    public function setPassword(string $username, string $password): bool
    {
        $user = $this->getUser($username);
        if (!$user) return false;
        $user['password'] = md5($password);
        $user['updated_at'] = now()->toISOString();
        $file = $this->usersDir() . '/' . $username . '.json';
        File::put($file, json_encode($user, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return true;
    }

    public function deleteUser(string $username): bool
    {
        $file = $this->usersDir() . '/' . $username . '.json';
        if (file_exists($file)) {
            unlink($file);
        }
        // Also delete user's session directory
        $sessionsDir = $this->sessionsDir($username);
        if (is_dir($sessionsDir)) {
            foreach (glob($sessionsDir . '/*.json') as $f) unlink($f);
            rmdir($sessionsDir);
        }
        return true;
    }

    public function listUserSessions(string $username): array
    {
        $sessions = [];
        foreach (glob($this->sessionsDir($username) . '/*.json') as $file) {
            $session = json_decode(file_get_contents($file), true);
            $session['id'] = basename($file, '.json');
            $sessions[] = $session;
        }
        usort($sessions, fn($a, $b) => ($b['updated_at'] ?? '') <=> ($a['updated_at'] ?? ''));
        return $sessions;
    }

    public function getSession(string $username, string $id): ?array
    {
        $file = $this->sessionsDir($username) . '/' . $id . '.json';
        if (!file_exists($file)) return null;
        $session = json_decode(file_get_contents($file), true);
        $session['id'] = $id;
        return $session;
    }

    public function saveSession(string $username, string $id, array $session): void
    {
        $file = $this->sessionsDir($username) . '/' . $id . '.json';
        File::put($file, json_encode($session, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public function createSession(string $username): array
    {
        $id = substr(md5(uniqid()), 0, 8) . '_' . time();
        $session = [
            'id' => $id,
            'username' => $username,
            'messages' => [],
            'created_at' => now()->toISOString(),
            'updated_at' => now()->toISOString(),
        ];
        $this->saveSession($username, $id, $session);
        return $session;
    }

    public function deleteSession(string $username, string $id): bool
    {
        $file = $this->sessionsDir($username) . '/' . $id . '.json';
        if (file_exists($file)) {
            unlink($file);
            return true;
        }
        return false;
    }
}
