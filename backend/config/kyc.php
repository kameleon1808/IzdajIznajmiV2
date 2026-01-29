<?php

return [
    'max_file_size_kb' => (int) env('KYC_MAX_FILE_SIZE_KB', 10240),
    'allowed_mimes' => ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
];
