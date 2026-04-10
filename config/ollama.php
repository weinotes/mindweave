<?php
/**
 * @author Davey (https://github.com/weinotes)
 */

return [
    "host" => env("OLLAMA_HOST", "http://localhost:11434"),
    "model" => env("OLLAMA_MODEL", "qwen2.5:3b"),
    "timeout" => env("OLLAMA_TIMEOUT", 300),
];

