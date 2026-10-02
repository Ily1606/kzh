# HƯỚNG DẪN GỢI Ý SỬA CHO CÁC VẤN ĐỀ THƯỜNG GẶP


#### 1. **N+1 Query Issues**:
```php
// ❌ BAD - Chỉ ra vấn đề
foreach ($posts as $post) {
    echo $post->user->name;
}

// ✅ GOOD - Đưa ra solution hoàn chỉnh
$posts = Post::with('user')->get();
foreach ($posts as $post) {
    echo $post->user->name;
}
````

#### 2. **Missing Validation**:

```php
// ❌ BAD
public function store(Request $request) {
    User::create($request->all());
}

// ✅ GOOD - Tạo FormRequest luôn
// Tạo file: app/Http/Requests/CreateUserRequest.php
class CreateUserRequest extends FormRequest {
    public function rules() {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8|confirmed'
        ];
    }
}

// Controller
public function store(CreateUserRequest $request) {
    User::create($request->validated());
}
```

#### 3. **Fat Controller**:

```php
// ❌ BAD - 50+ lines trong controller
public function store(Request $request) {
    // ... many lines of business logic
}

// ✅ GOOD - Tạo Service class
// Tạo file: app/Services/UserService.php
class UserService {
    public function createUser(array $data): User {
        // Business logic here
    }
}

// Controller
public function store(CreateUserRequest $request) {
    $user = $this->userService->createUser($request->validated());
    return new UserResource($user);
}
```

#### 4. **Security Issues**:

```php
// ❌ BAD - SQL Injection
$users = DB::select("SELECT * FROM users WHERE name = '$name'");

// ✅ GOOD - Parameter binding
$users = DB::select('SELECT * FROM users WHERE name = ?', [$name]);
// Hoặc Eloquent
$users = User::where('name', $name)->get();
```

#### 5. **Missing Authorization**:

```php
// ❌ BAD
public function delete(Post $post) {
    $post->delete();
}

// ✅ GOOD - Tạo Policy
// php artisan make:policy PostPolicy
class PostPolicy {
    public function delete(User $user, Post $post) {
        return $user->id === $post->user_id;
    }
}

// Controller
public function delete(Post $post) {
    $this->authorize('delete', $post);
    $post->delete();
}
```

---


#### 6. **Fully Qualified Class Names (FQCN) trong code**:
```php
// ❌ BAD - Sử dụng FQCN trực tiếp trong code
public function handle(string $id): \App\DTOs\PluginViewResult
{
    return new \App\DTOs\PluginViewResult(true, 1);
}

// ✅ GOOD - Import bằng lệnh use ở đầu file
use App\DTOs\PluginViewResult;

public function handle(string $id): PluginViewResult
{
    return new PluginViewResult(true, 1);
}
```
