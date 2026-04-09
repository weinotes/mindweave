<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MindWeave - 请登录</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0a0a0f 0%, #12121a 100%);
            font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', sans-serif;
            color: #e4e4e7;
        }
        .login-box {
            background: #16161e;
            border: 1px solid #2a2a3a;
            border-radius: 20px;
            padding: 36px;
            width: 380px;
            max-width: 90vw;
        }
        .login-box .logo { font-size: 26px; margin-bottom: 6px; color: #c9a85c; font-weight: 600; }
        .login-box .subtitle { color: #71717a; font-size: 13px; margin-bottom: 28px; }
        .login-box .field {
            margin-bottom: 14px;
        }
        .login-box label {
            display: block;
            font-size: 12px;
            color: #71717a;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .login-box input,
        .login-box select {
            width: 100%;
            padding: 12px 14px;
            background: #1a1a24;
            border: 1px solid #2a2a3a;
            border-radius: 10px;
            color: #e4e4e7;
            font-size: 14px;
            outline: none;
            transition: all 0.2s;
            appearance: none;
            -webkit-appearance: none;
            height: 46px;
            line-height: 1;
        }

        .login-box select {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%2371717a' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            padding-right: 36px;
            cursor: pointer;
        }
        .login-box input:focus,
        .login-box select:focus {
            border-color: #c9a85c;
            box-shadow: 0 0 0 3px rgba(201,168,92,0.2);
        }
        .login-box button {
            width: 100%;
            padding: 13px;
            background: linear-gradient(135deg, #c9a85c, #b8956a);
            color: #0a0a0f;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 8px;
            transition: all 0.2s;
        }
        .login-box button:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 16px rgba(201,168,92,0.35);
        }
        .error {
            color: #ef4444;
            font-size: 12px;
            margin-top: 12px;
            padding: 8px 12px;
            background: rgba(239,68,68,0.1);
            border-radius: 8px;
            display: none;
        }
        .hint {
            color: #71717a;
            font-size: 11px;
            margin-top: 20px;
            line-height: 1.6;
        }
        .hint code {
            color: #c9a85c;
            font-family: monospace;
            background: #1a1a24;
            padding: 1px 5px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="login-box">
        <div class="logo">✨ MindWeave</div>
        <div class="subtitle">本地大模型工作台</div>

        @if(count($users) > 0)
            <form method="POST" action="/login">
                @csrf
                <div class="field">
                    <label>用户名</label>
                    <select name="username" autofocus required>
                        <option value="">选择用户...</option>
                        @foreach($users as $u)
                            <option value="{{ $u['username'] }}">{{ $u['username'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>密码</label>
                    <input type="password" name="password" placeholder="输入密码...">
                </div>
                <button type="submit">进入</button>
            </form>
        @else
            <form method="POST" action="/login">
                @csrf
                <input type="hidden" name="username" value="guest">
                <p style="color:#71717a;font-size:13px;margin-bottom:16px;">首次使用，自动以访客身份进入。<br>可从设置中创建多人账号。</p>
                <button type="submit">开始使用</button>
            </form>
        @endif

        @if($errors->any())
            <p class="error" style="display:block;">{{ $errors->first() }}</p>
        @endif
    </div>
</body>
</html>
