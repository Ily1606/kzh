<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | IP/CIDR của reverse proxy (nginx, ALB, Cloudflare...) được tin để đọc
    | X-Forwarded-For, giúp $request->ip() trả về IP thật của client cho
    | audit log và các limiter tính theo IP.
    |
    | MẶC ĐỊNH KHÔNG TIN PROXY NÀO (null). App có thể nhận kết nối trực tiếp
    | (ví dụ docker-compose map thẳng 8000:8000), khi đó nếu tin
    | X-Forwarded-For thì client tự giả được IP để vượt throttle:auth,
    | brute-force /login, /forgot-password, /reset-password.
    |
    | Chỉ điền khi app THẬT SỰ chạy sau LB/reverse proxy, và điền đúng dải
    | CIDR của LB đó. Tránh dùng '*' trừ khi chắc chắn app không thể bị kết
    | nối trực tiếp (đã chặn bằng firewall/security group).
    |
    | Đây là NGUỒN SỰ THẬT DUY NHẤT. Middleware TrustProxies (global) tự
    | đọc key này lúc runtime (mỗi request), khi config đã load xong.
    | bootstrap/app.php chỉ gọi trustProxies() để đảm bảo middleware bật,
    | không truyền giá trị ở đó (ở đó chưa gọi được config()/env()).
    |
    | Ví dụ: TRUSTED_PROXIES=10.0.0.0/8,172.16.0.0/12,192.168.0.0/16
    |
    */

    // Chuỗi rỗng trong .env (TRUSTED_PROXIES=) cũng được quy về null.
    'proxies' => env('TRUSTED_PROXIES') ?: null,

];
