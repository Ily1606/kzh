<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | IP/CIDR của reverse proxy (nginx, ALB, Cloudflare...) được tin để đọc
    | X-Forwarded-For, giúp $request->ip() trả về IP thật của client cho
    | audit log. Dùng '*' khi app luôn chạy sau proxy.
    |
    | Đây là NGUỒN SỰ THẬT DUY NHẤT. Middleware TrustProxies (global) tự
    | đọc key này lúc runtime (mỗi request), khi config đã load xong.
    | bootstrap/app.php chỉ gọi trustProxies() để đảm bảo middleware bật,
    | không truyền giá trị ở đó (ở đó chưa gọi được config()/env()).
    |
    | Ví dụ: TRUSTED_PROXIES=10.0.0.0/8,172.16.0.0/12,192.168.0.0/16
    |
    */

    'proxies' => env('TRUSTED_PROXIES', '*'),

];
