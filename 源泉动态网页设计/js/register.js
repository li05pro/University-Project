
        // 检测是否通过 HTTP 服务器访问
        if (window.location.protocol === 'file:') {
            document.body.innerHTML = '<div style="padding: 50px; text-align: center; font-family: Arial;"><h1 style="color: #e74c3c;">⚠️ 访问方式错误</h1><p style="font-size: 18px; margin: 20px 0;">您正在通过 <strong>file://</strong> 协议直接打开文件</p><p style="font-size: 16px; color: #666;">请通过 HTTP 服务器访问：</p><p style="font-size: 20px; background: #f5f5f5; padding: 15px; border-radius: 8px; margin: 20px auto; max-width: 500px;"><a href="http://localhost/源泉/register.html" style="color: #1abc9c; text-decoration: none;">http://localhost/源泉/register.html</a></p><p style="color: #999; margin-top: 30px;">请确保 phpstudy_pro 的 Apache 服务已启动</p></div>';
            throw new Error('必须通过 HTTP 服务器访问');
        }

        // 显示消息提示
        function showMessage(message, type) {
            // 移除旧的消息
            const oldMsg = document.querySelector('.message-box');
            if (oldMsg) oldMsg.remove();
            
            // 创建消息元素
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
            
            // 3秒后自动消失
            setTimeout(() => {
                msgBox.remove();
            }, 3000);
        }

        // 注册表单提交处理
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = '注册中...';
            
            const formData = new FormData(this);
            
            fetch('php/auth.php?action=register', {
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
                    showMessage(data.message || '注册成功！即将跳转到登录页面...', 'success');
                    setTimeout(() => {
                        window.location.href = 'login.html?registered=1&username=' + encodeURIComponent(formData.get('username'));
                    }, 2000);
                } else {
                    showMessage(data.message || '注册失败', 'error');
                    document.querySelector('.captcha-box img').src = 'php/captcha.php?' + Math.random();
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }
            })
            .catch(error => {
                console.error('注册错误:', error);
                showMessage('注册失败：' + error.message, 'error');
                document.querySelector('.captcha-box img').src = 'php/captcha.php?' + Math.random();
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            });
        });
        
        // 用户名实时检查
        document.querySelector('input[name="username"]').addEventListener('blur', function() {
            const username = this.value.trim();
            if (username && /^[a-zA-Z0-9_]{3,20}$/.test(username)) {
                fetch('php/auth.php?action=check_username&username=' + encodeURIComponent(username))
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        this.classList.add('error');
                        let errorSpan = this.parentNode.querySelector('.form-error');
                        if (!errorSpan) {
                            errorSpan = document.createElement('span');
                            errorSpan.className = 'form-error';
                            this.parentNode.appendChild(errorSpan);
                        }
                        errorSpan.textContent = data.message;
                    }
                });
            }
        });
        
        // 邮箱实时检查
        document.querySelector('input[name="email"]').addEventListener('blur', function() {
            const email = this.value.trim();
            if (email && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                fetch('php/auth.php?action=check_email&email=' + encodeURIComponent(email))
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        this.classList.add('error');
                        let errorSpan = this.parentNode.querySelector('.form-error');
                        if (!errorSpan) {
                            errorSpan = document.createElement('span');
                            errorSpan.className = 'form-error';
                            this.parentNode.appendChild(errorSpan);
                        }
                        errorSpan.textContent = data.message;
                    }
                });
            }
        });
    