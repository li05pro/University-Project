/**
 * 源泉动态网站 - 主要JavaScript文件
 */

// DOM加载完成后执行
document.addEventListener('DOMContentLoaded', function() {
    // 初始化所有功能
    initBackToTop();
    initSearchBox();
    initFormValidation();
    initMobileMenu();
    initDropdowns();
});

/**
 * 回到顶部功能
 */
function initBackToTop() {
    const backToTopBtn = document.querySelector('.back-to-top');
    if (!backToTopBtn) return;
    
    window.addEventListener('scroll', function() {
        if (window.pageYOffset > 300) {
            backToTopBtn.classList.add('show');
        } else {
            backToTopBtn.classList.remove('show');
        }
    });
    
    backToTopBtn.addEventListener('click', function() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });
}

/**
 * 搜索框功能
 */
function initSearchBox() {
    const searchInput = document.querySelector('.search-box input');
    if (!searchInput) return;
    
    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            const keyword = this.value.trim();
            if (keyword) {
                window.location.href = 'search.php?keyword=' + encodeURIComponent(keyword);
            }
        }
    });
}

/**
 * 表单验证
 */
function initFormValidation() {
    const forms = document.querySelectorAll('form[data-validate]');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            let isValid = true;
            const requiredFields = form.querySelectorAll('[required]');
            
            requiredFields.forEach(field => {
                if (!validateField(field)) {
                    isValid = false;
                }
            });
            
            if (!isValid) {
                e.preventDefault();
            }
        });
        
        // 实时验证
        const fields = form.querySelectorAll('input, textarea, select');
        fields.forEach(field => {
            field.addEventListener('blur', function() {
                validateField(this);
            });
            
            field.addEventListener('input', function() {
                if (this.classList.contains('error')) {
                    validateField(this);
                }
            });
        });
    });
}

/**
 * 验证单个字段
 */
function validateField(field) {
    const value = field.value.trim();
    const type = field.type;
    const name = field.name;
    let isValid = true;
    let errorMsg = '';
    
    // 清除之前的错误状态
    field.classList.remove('error');
    const existingError = field.parentNode.querySelector('.form-error');
    if (existingError) {
        existingError.remove();
    }
    
    // 必填验证
    if (field.required && !value) {
        isValid = false;
        errorMsg = '此项为必填项';
    }
    
    // 根据字段类型进行特定验证
    if (isValid && value) {
        switch (name) {
            case 'username':
                if (!/^[a-zA-Z0-9_]{3,20}$/.test(value)) {
                    isValid = false;
                    errorMsg = '用户名只能包含字母、数字、下划线，长度3-20位';
                }
                break;
                
            case 'email':
                if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                    isValid = false;
                    errorMsg = '请输入有效的邮箱地址';
                }
                break;
                
            case 'password':
                if (value.length < 6) {
                    isValid = false;
                    errorMsg = '密码长度至少6位';
                } else if (!/(?=.*[a-zA-Z])(?=.*\d)/.test(value)) {
                    isValid = false;
                    errorMsg = '密码必须包含字母和数字';
                }
                break;
                
            case 'confirm_password':
                const password = document.querySelector('input[name="password"]');
                if (password && value !== password.value) {
                    isValid = false;
                    errorMsg = '两次输入的密码不一致';
                }
                break;
                
            case 'nickname':
                if (value.length < 2 || value.length > 50) {
                    isValid = false;
                    errorMsg = '昵称长度应在2-50个字符之间';
                }
                break;
                
            case 'title':
                if (value.length < 2 || value.length > 200) {
                    isValid = false;
                    errorMsg = '标题长度应在2-200个字符之间';
                }
                break;
                
            case 'content':
                if (value.length < 10) {
                    isValid = false;
                    errorMsg = '内容至少10个字符';
                }
                break;
        }
    }
    
    // 显示错误信息
    if (!isValid) {
        field.classList.add('error');
        const errorSpan = document.createElement('span');
        errorSpan.className = 'form-error';
        errorSpan.textContent = errorMsg;
        field.parentNode.appendChild(errorSpan);
    }
    
    return isValid;
}

/**
 * 移动端菜单
 */
function initMobileMenu() {
    const menuToggle = document.querySelector('.menu-toggle');
    const navMenu = document.querySelector('.nav-menu');
    
    if (menuToggle && navMenu) {
        menuToggle.addEventListener('click', function() {
            navMenu.classList.toggle('show');
        });
    }
}

/**
 * 下拉菜单
 */
function initDropdowns() {
    const dropdowns = document.querySelectorAll('.dropdown');
    
    dropdowns.forEach(dropdown => {
        const toggle = dropdown.querySelector('.dropdown-toggle');
        const menu = dropdown.querySelector('.dropdown-menu');
        
        if (toggle && menu) {
            toggle.addEventListener('click', function(e) {
                e.stopPropagation();
                menu.classList.toggle('show');
            });
        }
    });
    
    // 点击外部关闭下拉菜单
    document.addEventListener('click', function() {
        document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
            menu.classList.remove('show');
        });
    });
}

/**
 * AJAX请求封装
 */
function ajax(options) {
    const defaultOptions = {
        method: 'GET',
        data: null,
        headers: {},
        success: null,
        error: null
    };
    
    const opts = Object.assign({}, defaultOptions, options);
    const xhr = new XMLHttpRequest();
    
    xhr.open(opts.method, opts.url, true);
    
    // 设置请求头
    Object.keys(opts.headers).forEach(key => {
        xhr.setRequestHeader(key, opts.headers[key]);
    });
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status >= 200 && xhr.status < 300) {
                let response = xhr.responseText;
                try {
                    response = JSON.parse(response);
                } catch (e) {}
                if (opts.success) opts.success(response);
            } else {
                if (opts.error) opts.error(xhr);
            }
        }
    };
    
    xhr.send(opts.data);
}

/**
 * 显示提示消息
 */
function showMessage(message, type = 'info', duration = 3000) {
    // 移除已存在的消息
    const existingAlert = document.querySelector('.alert-float');
    if (existingAlert) {
        existingAlert.remove();
    }
    
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-float`;
    alert.textContent = message;
    alert.style.cssText = `
        position: fixed;
        top: 80px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 9999;
        min-width: 200px;
        text-align: center;
        animation: slideDown 0.3s ease;
    `;
    
    document.body.appendChild(alert);
    
    setTimeout(() => {
        alert.style.animation = 'slideUp 0.3s ease';
        setTimeout(() => alert.remove(), 300);
    }, duration);
}

/**
 * 确认对话框
 */
function confirmDialog(message, onConfirm, onCancel) {
    const modal = document.createElement('div');
    modal.className = 'confirm-modal';
    modal.innerHTML = `
        <div class="confirm-content">
            <p>${message}</p>
            <div class="confirm-buttons">
                <button class="btn btn-outline btn-sm cancel">取消</button>
                <button class="btn btn-primary btn-sm confirm">确定</button>
            </div>
        </div>
    `;
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10000;
    `;
    
    const content = modal.querySelector('.confirm-content');
    content.style.cssText = `
        background: white;
        padding: 30px;
        border-radius: 8px;
        text-align: center;
        max-width: 300px;
    `;
    
    modal.querySelector('.cancel').addEventListener('click', () => {
        modal.remove();
        if (onCancel) onCancel();
    });
    
    modal.querySelector('.confirm').addEventListener('click', () => {
        modal.remove();
        if (onConfirm) onConfirm();
    });
    
    document.body.appendChild(modal);
}

/**
 * 图片预览
 */
function previewImage(input, preview) {
    const file = input.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            if (typeof preview === 'string') {
                document.querySelector(preview).src = e.target.result;
            } else {
                preview.src = e.target.result;
            }
        };
        reader.readAsDataURL(file);
    }
}

/**
 * 字符计数
 */
function charCount(input, counter, maxLength) {
    const current = input.value.length;
    counter.textContent = `${current}/${maxLength}`;
    
    if (current > maxLength) {
        counter.style.color = '#e74c3c';
    } else {
        counter.style.color = '#7f8c8d';
    }
}

/**
 * 懒加载图片
 */
function lazyLoadImages() {
    const images = document.querySelectorAll('img[data-src]');
    
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.removeAttribute('data-src');
                observer.unobserve(img);
            }
        });
    });
    
    images.forEach(img => imageObserver.observe(img));
}

// 添加CSS动画
const style = document.createElement('style');
style.textContent = `
    @keyframes slideDown {
        from { opacity: 0; transform: translate(-50%, -20px); }
        to { opacity: 1; transform: translate(-50%, 0); }
    }
    @keyframes slideUp {
        from { opacity: 1; transform: translate(-50%, 0); }
        to { opacity: 0; transform: translate(-50%, -20px); }
    }
`;
document.head.appendChild(style);
