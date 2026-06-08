/**
 * 源泉动态网站 - 用户认证与登录状态管理
 */

// 用户认证对象
const Auth = {
    // 存储键名
    STORAGE_KEY: 'yuanquan_user',
    TOKEN_KEY: 'yuanquan_token',

    /**
     * 获取当前登录用户信息
     * @returns {Object|null} 用户信息或null
     */
    getUser() {
        const userData = localStorage.getItem(this.STORAGE_KEY);
        if (userData) {
            try {
                return JSON.parse(userData);
            } catch (e) {
                return null;
            }
        }
        return null;
    },

    /**
     * 保存用户信息到本地存储
     * @param {Object} userData 用户数据
     */
    setUser(userData) {
        if (userData) {
            localStorage.setItem(this.STORAGE_KEY, JSON.stringify(userData));
        }
    },

    /**
     * 清除用户登录状态
     */
    clearUser() {
        localStorage.removeItem(this.STORAGE_KEY);
        localStorage.removeItem(this.TOKEN_KEY);
        sessionStorage.removeItem('login_success');
        sessionStorage.removeItem('username');
    },

    /**
     * 检查用户是否已登录
     * @returns {boolean}
     */
    isLoggedIn() {
        const user = this.getUser();
        return user && user.user_id > 0;
    },

    /**
     * 获取用户ID
     * @returns {number|null}
     */
    getUserId() {
        const user = this.getUser();
        return user ? user.user_id : null;
    },

    /**
     * 获取用户名
     * @returns {string|null}
     */
    getUsername() {
        const user = this.getUser();
        return user ? user.username : null;
    },

    /**
     * 获取用户昵称
     * @returns {string|null}
     */
    getNickname() {
        const user = this.getUser();
        return user ? (user.nickname || user.username) : null;
    },

    /**
     * 获取用户头像
     * @returns {string|null}
     */
    getAvatar() {
        const user = this.getUser();
        return user ? (user.avatar || null) : null;
    },

    /**
     * 从服务器检查登录状态并更新本地存储
     * @returns {Promise<Object>}
     */
    async checkLoginStatus() {
        try {
            const response = await fetch('php/auth.php?action=check_login', {
                method: 'GET',
                credentials: 'same-origin'
            });
            const data = await response.json();

            if (data.success && data.data && data.data.logged_in) {
                this.setUser(data.data.user);
                return { loggedIn: true, user: data.data.user };
            } else {
                this.clearUser();
                return { loggedIn: false, user: null };
            }
        } catch (error) {
            console.error('检查登录状态失败:', error);
            return { loggedIn: false, user: null, error: error.message };
        }
    },

    /**
     * 退出登录
     * @returns {Promise<boolean>}
     */
    async logout() {
        try {
            await fetch('php/auth.php?action=logout', {
                method: 'GET',
                credentials: 'same-origin'
            });
        } catch (error) {
            console.error('退出登录请求失败:', error);
        } finally {
            this.clearUser();
            window.location.href = 'index.html';
        }
    },

    /**
     * 更新页面导航栏用户菜单显示
     */
    updateUserMenu() {
        const userMenuContainers = document.querySelectorAll('.user-menu');

        userMenuContainers.forEach(container => {
            if (this.isLoggedIn()) {
                const nickname = this.getNickname();
                const avatar = this.getAvatar();
                const avatarHtml = avatar
                    ? `<img src="${avatar}" alt="${nickname}" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover; margin-right: 8px;">`
                    : `<span style="width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, #1abc9c 0%, #16a085 100%); color: white; display: inline-flex; align-items: center; justify-content: center; margin-right: 8px; font-size: 14px;">${nickname.charAt(0).toUpperCase()}</span>`;

                container.innerHTML = `
                    <div class="dropdown" style="position: relative;">
                        <a href="#" class="dropdown-toggle" style="display: flex; align-items: center; text-decoration: none; color: #2c3e50;">
                            ${avatarHtml}
                            <span>${nickname}</span>
                            <span style="margin-left: 5px;">▼</span>
                        </a>
                        <div class="dropdown-menu" style="display: none; position: absolute; top: 100%; right: 0; background: white; box-shadow: 0 4px 12px rgba(0,0,0,0.15); border-radius: 8px; min-width: 150px; margin-top: 8px; z-index: 1000;">
                            <a href="user-center.html" style="display: block; padding: 12px 20px; color: #2c3e50; text-decoration: none; border-bottom: 1px solid #f0f0f0;">👤 个人中心</a>
                            <a href="publish.html" style="display: block; padding: 12px 20px; color: #2c3e50; text-decoration: none; border-bottom: 1px solid #f0f0f0;">✍️ 发布资讯</a>
                            <a href="#" onclick="Auth.logout(); return false;" style="display: block; padding: 12px 20px; color: #e74c3c; text-decoration: none;">🚪 退出登录</a>
                        </div>
                    </div>
                `;

                // 绑定下拉菜单事件
                const dropdownToggle = container.querySelector('.dropdown-toggle');
                const dropdownMenu = container.querySelector('.dropdown-menu');

                if (dropdownToggle && dropdownMenu) {
                    dropdownToggle.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        const isVisible = dropdownMenu.style.display === 'block';
                        dropdownMenu.style.display = isVisible ? 'none' : 'block';
                    });

                    // 点击外部关闭下拉菜单
                    document.addEventListener('click', function() {
                        dropdownMenu.style.display = 'none';
                    });
                }
            } else {
                container.innerHTML = `
                    <a href="login.html" class="btn btn-outline btn-sm">登录</a>
                    <a href="register.html" class="btn btn-primary btn-sm">注册</a>
                `;
            }
        });
    },

    /**
     * 初始化认证系统
     */
    init() {
        // 页面加载时更新用户菜单
        this.updateUserMenu();

        // 检查登录状态（异步，用于同步服务器session状态）
        this.checkLoginStatus().then(result => {
            if (result.loggedIn !== this.isLoggedIn()) {
                // 如果服务器状态与本地不一致，更新显示
                this.updateUserMenu();
            }
        });
    },

    /**
     * 需要登录才能访问的页面检查
     * @param {string} redirectUrl 未登录时跳转的URL
     */
    requireLogin(redirectUrl = 'login.html') {
        if (!this.isLoggedIn()) {
            window.location.href = redirectUrl;
            return false;
        }
        return true;
    }
};

// DOM加载完成后初始化
document.addEventListener('DOMContentLoaded', function() {
    Auth.init();
});
