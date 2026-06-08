
        // 显示消息提示
        function showMessage(message, type) {
            const oldMsg = document.querySelector('.message-box');
            if (oldMsg) oldMsg.remove();
            
            const msgBox = document.createElement('div');
            msgBox.className = 'message-box message-' + type;
            msgBox.style.cssText = 'position: fixed; top: 20px; left: 50%; transform: translateX(-50%); padding: 15px 30px; border-radius: 8px; z-index: 9999; font-size: 14px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);';
            
            if (type === 'success') {
                msgBox.style.background = '#d4edda';
                msgBox.style.color = '#155724';
                msgBox.style.border = '1px solid #c3e6cb';
            } else {
                msgBox.style.background = '#f8d7da';
                msgBox.style.color = '#721c24';
                msgBox.style.border = '1px solid #f5c6cb';
            }
            
            msgBox.textContent = message;
            document.body.appendChild(msgBox);
            
            setTimeout(() => {
                msgBox.remove();
            }, 3000);
        }

        // 检查URL参数，显示注册成功提示
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('registered') === '1') {
            const username = urlParams.get('username') || '';
            showMessage('注册成功！请使用您的账号登录', 'success');
            if (username) {
                document.querySelector('input[name="username"]').value = username;
            }
        }
        
        // 登录表单提交处理
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = '登录中...';
            
            const formData = new FormData(this);
            
            fetch('php/auth.php?action=login', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => {
                return response.text().then(text => {
                    console.log('服务器响应:', text);
                    if (!text || text.trim() === '') {
                        throw new Error('服务器返回空响应');
                    }
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('JSON解析错误:', e, '原始文本:', text);
                        throw new Error('服务器返回数据格式错误');
                    }
                });
            })
            .then(data => {
                console.log('解析后的数据:', data);
                if (data.success) {
                    showMessage(data.message || '登录成功！正在跳转到首页...', 'success');
                    // 保存用户信息到本地存储，实现跨页面登录状态保持
                    const userData = {
                        user_id: data.data?.user_id || 0,
                        username: data.data?.username || formData.get('username'),
                        nickname: data.data?.nickname || data.data?.username || formData.get('username'),
                        avatar: data.data?.avatar || null
                    };
                    localStorage.setItem('yuanquan_user', JSON.stringify(userData));
                    localStorage.setItem('yuanquan_token', 'logged_in');
                    setTimeout(() => {
                        window.location.href = (data.data && data.data.redirect) ? data.data.redirect : 'index.html';
                    }, 1500);
                } else {
                    showMessage(data.message || '登录失败', 'error');
                    document.querySelector('.captcha-box img').src = 'php/captcha.php?' + Math.random();
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }
            })
            .catch(error => {
                console.error('登录错误:', error);
                showMessage('登录失败：' + error.message, 'error');
                document.querySelector('.captcha-box img').src = 'php/captcha.php?' + Math.random();
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            });
        });