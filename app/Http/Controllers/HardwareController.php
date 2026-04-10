<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HardwareController extends Controller
{
    /**
     * 获取本地硬件信息
     */
    public function info()
    {
        $info = [
            'os' => php_uname('s') . ' ' . php_uname('r'),
            'cpu' => $this->getCPUInfo(),
            'memory' => $this->getMemoryInfo(),
            'gpu' => $this->getGPUInfo(),
            'ollama' => $this->getOllamaVersion(),
        ];

        return response()->json($info);
    }

    /**
     * 推荐模型列表
     */
    public function recommend()
    {
        $hardware = $this->getHardwareSummary();
        
        $recommendations = $this->getModelRecommendations($hardware);
        
        return response()->json([
            'hardware' => $hardware,
            'recommendations' => $recommendations,
        ]);
    }

    private function getCPUInfo()
    {
        $cpu = ['cores' => 1, 'model' => 'Unknown'];
        
        if (PHP_OS_FAMILY === 'Darwin') {
            $cores = (int) shell_exec('sysctl -n hw.ncpu 2>/dev/null') ?: 1;
            $model = shell_exec('sysctl -n machdep.cpu.brand_string 2>/dev/null') ?: 'Apple Silicon';
            $cpu = ['cores' => $cores, 'model' => trim($model)];
        } elseif (PHP_OS_FAMILY === 'Linux') {
            $cores = (int) shell_exec('nproc 2>/dev/null') ?: 1;
            $model = shell_exec('cat /proc/cpuinfo | grep "model name" | head -1 | cut -d: -f2 2>/dev/null') ?: 'Unknown';
            $cpu = ['cores' => $cores, 'model' => trim($model)];
        } elseif (PHP_OS_FAMILY === 'Windows') {
            $cores = (int) shell_exec('cmd /c echo %NUMBER_OF_PROCESSORS%') ?: 1;
            $cpu = ['cores' => $cores, 'model' => 'Windows CPU'];
        }

        return $cpu;
    }

    private function getMemoryInfo()
    {
        $memory = ['total' => 0, 'available' => 0, 'unit' => 'GB'];

        if (PHP_OS_FAMILY === 'Darwin') {
            $bytes = (int) shell_exec('sysctl -n hw.memsize 2>/dev/null') ?: 0;
            $total = round($bytes / 1024 / 1024 / 1024, 1);
            
            // 获取可用内存 (free + inactive + speculative)
            $vmStat = shell_exec('vm_stat 2>/dev/null');
            if ($vmStat) {
                $freePages = 0;
                $inactivePages = 0;
                $speculativePages = 0;
                
                if (preg_match('/Pages free:\s+(\d+)/', $vmStat, $matches)) {
                    $freePages = (int) $matches[1];
                }
                if (preg_match('/Pages inactive:\s+(\d+)/', $vmStat, $matches)) {
                    $inactivePages = (int) $matches[1];
                }
                if (preg_match('/Pages speculative:\s+(\d+)/', $vmStat, $matches)) {
                    $speculativePages = (int) $matches[1];
                }
                
                // 真正可用 = free + inactive + speculative
                $availablePages = $freePages + $inactivePages + $speculativePages;
                $available = round($availablePages * 4096 / 1024 / 1024 / 1024, 1);
            } else {
                $available = $total;
            }
            
            $memory = ['total' => $total, 'available' => $available, 'unit' => 'GB'];
        } elseif (PHP_OS_FAMILY === 'Linux') {
            $memInfo = shell_exec('cat /proc/meminfo 2>/dev/null');
            if ($memInfo) {
                preg_match('/MemTotal:\s+(\d+)/', $memInfo, $total);
                preg_match('/MemAvailable:\s+(\d+)/', $memInfo, $avail);
                $totalKB = isset($total[1]) ? (int) $total[1] : 0;
                $availKB = isset($avail[1]) ? (int) $avail[1] : $totalKB;
                $memory = [
                    'total' => round($totalKB / 1024 / 1024, 1),
                    'available' => round($availKB / 1024 / 1024, 1),
                    'unit' => 'GB'
                ];
            }
        }

        return $memory;
    }

    private function getGPUInfo()
    {
        $gpu = ['available' => false, 'models' => [], 'vram' => 0];

        if (PHP_OS_FAMILY === 'Darwin') {
            // 检测 Apple Silicon
            $cpuModel = shell_exec('sysctl -n machdep.cpu.brand_string 2>/dev/null') ?: '';
            if (stripos($cpuModel, 'Apple') !== false) {
                $gpu['available'] = true;
                $gpu['models'][] = 'Apple Silicon (Unified Memory)';
                $gpu['vram'] = 'shared'; // 统一内存架构
                $gpu['type'] = 'apple_silicon';
            }
            
            // 检测独立显卡
            $displays = shell_exec('system_profiler SPDisplaysDataType 2>/dev/null');
            if ($displays && preg_match('/Chipset Model:\s*(.+)/m', $displays, $matches)) {
                $gpu['models'][] = trim($matches[1]);
            }
        } elseif (PHP_OS_FAMILY === 'Linux') {
            // 检测 NVIDIA GPU
            $nvidia = shell_exec('nvidia-smi --query-gpu=name,memory.total --format=csv,noheader 2>/dev/null');
            if ($nvidia) {
                $gpu['available'] = true;
                $gpu['type'] = 'nvidia';
                foreach (explode("\n", trim($nvidia)) as $line) {
                    if ($line) {
                        $parts = str_getcsv($line);
                        $gpu['models'][] = trim($parts[0]);
                        if (isset($parts[1])) {
                            preg_match('/(\d+)/', $parts[1], $vram);
                            $gpu['vram'] = isset($vram[1]) ? (int) $vram[1] : 0;
                        }
                    }
                }
            }
        }

        return $gpu;
    }

    private function getOllamaVersion()
    {
        $version = shell_exec('ollama --version 2>/dev/null');
        if ($version) {
            preg_match('/(\d+\.\d+\.\d+)/', $version, $matches);
            return isset($matches[1]) ? $matches[1] : 'installed';
        }
        return null;
    }

    private function getHardwareSummary()
    {
        return [
            'cpu_cores' => $this->getCPUInfo()['cores'],
            'memory_gb' => $this->getMemoryInfo()['total'],
            'available_memory_gb' => $this->getMemoryInfo()['available'],
            'gpu' => $this->getGPUInfo()['available'],
            'gpu_type' => $this->getGPUInfo()['type'] ?? null,
            'gpu_models' => $this->getGPUInfo()['models'],
            'vram_mb' => $this->getGPUInfo()['vram'] ?? 0,
        ];
    }

    private function getModelRecommendations($hw)
    {
        $models = [];
        $ram = $hw['memory_gb'];
        $availableRam = $hw['available_memory_gb'];
        $hasAppleSilicon = ($hw['gpu_type'] ?? '') === 'apple_silicon';
        $hasNvidia = ($hw['gpu_type'] ?? '') === 'nvidia';

        // 基础推荐模型库
        $modelLibrary = [
            // 小模型（适合所有配置）
            'tiny' => [
                ['name' => 'qwen2.5:0.5b', 'size' => '0.4GB', 'params' => '0.5B', 'description' => '最轻量级，适合测试'],
                ['name' => 'qwen2.5:1.5b', 'size' => '1.1GB', 'params' => '1.5B', 'description' => '轻量级，快速响应'],
                ['name' => 'gemma2:2b', 'size' => '1.6GB', 'params' => '2B', 'description' => 'Google轻量模型'],
            ],
            // 小型模型（4-8GB内存）
            'small' => [
                ['name' => 'qwen2.5:3b', 'size' => '2.1GB', 'params' => '3B', 'description' => '性能与速度平衡'],
                ['name' => 'llama3.2:3b', 'size' => '2.0GB', 'params' => '3B', 'description' => 'Meta最新小模型'],
                ['name' => 'phi3:3.8b', 'size' => '2.3GB', 'params' => '3.8B', 'description' => '微软小而强模型'],
            ],
            // 中型模型（8-16GB内存）
            'medium' => [
                ['name' => 'qwen2.5:7b', 'size' => '4.7GB', 'params' => '7B', 'description' => '推荐：通用能力强'],
                ['name' => 'llama3.1:8b', 'size' => '4.7GB', 'params' => '8B', 'description' => '推荐：综合性能优秀'],
                ['name' => 'mistral:7b', 'size' => '4.1GB', 'params' => '7B', 'description' => '高效推理模型'],
                ['name' => 'gemma2:9b', 'size' => '5.5GB', 'params' => '9B', 'description' => 'Google中型模型'],
            ],
            // 大型模型（16-32GB内存）
            'large' => [
                ['name' => 'qwen2.5:14b', 'size' => '9GB', 'params' => '14B', 'description' => '强推理能力'],
                ['name' => 'llama3.1:13b', 'size' => '7.4GB', 'params' => '13B', 'description' => 'Meta大型模型'],
                ['name' => 'codellama:13b', 'size' => '7.4GB', 'params' => '13B', 'description' => '代码专用'],
            ],
            // 超大模型（32GB+内存）
            'xlarge' => [
                ['name' => 'qwen2.5:32b', 'size' => '20GB', 'params' => '32B', 'description' => '高性能推理'],
                ['name' => 'llama3.1:70b', 'size' => '40GB', 'params' => '70B', 'description' => '最强开源模型'],
                ['name' => 'deepseek-coder:33b', 'size' => '19GB', 'params' => '33B', 'description' => '代码大模型'],
            ],
        ];

        // 根据硬件配置推荐
        if ($ram < 8) {
            $models = $modelLibrary['tiny'];
            $level = 'tiny';
        } elseif ($ram < 16) {
            $models = array_merge($modelLibrary['tiny'], $modelLibrary['small']);
            $level = 'small';
        } elseif ($ram < 32) {
            $models = array_merge($modelLibrary['tiny'], $modelLibrary['small'], $modelLibrary['medium']);
            $level = 'medium';
        } elseif ($ram < 48) {
            $models = array_merge($modelLibrary['tiny'], $modelLibrary['small'], $modelLibrary['medium'], $modelLibrary['large']);
            $level = 'large';
        } else {
            $models = array_merge($modelLibrary['tiny'], $modelLibrary['small'], $modelLibrary['medium'], $modelLibrary['large'], $modelLibrary['xlarge']);
            $level = 'xlarge';
        }

        // Apple Silicon 特殊优化推荐
        if ($hasAppleSilicon) {
            foreach ($models as &$m) {
                $m['optimized'] = true;
                $m['optimization_note'] = 'Apple Silicon优化';
            }
        }

        return [
            'level' => $level,
            'reason' => $this->getRecommendationReason($ram, $hasAppleSilicon),
            'models' => $models,
        ];
    }

    private function getRecommendationReason($ram, $hasAppleSilicon)
    {
        if ($hasAppleSilicon) {
            return "检测到 Apple Silicon，统一内存架构可高效运行模型。当前 {$ram}GB 内存";
        }
        return "基于 {$ram}GB 内存配置推荐";
    }
}
