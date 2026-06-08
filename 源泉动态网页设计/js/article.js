/**
 * 源泉动态网站 - 文章管理模块
 * 实现文章列表加载、分页、搜索等功能
 */

// 文章管理对象
const ArticleManager = {
    currentPage: 1,
    pageSize: 10,
    categoryId: 0,
    keyword: '',
    isLoading: false,
    totalPages: 1,

    /**
     * 初始化
     */
    init() {
        // 从URL获取参数
        this.categoryId = parseInt(this.getUrlParam('category')) || 0;
        this.currentPage = parseInt(this.getUrlParam('page')) || 1;
        this.keyword = this.getUrlParam('keyword') || '';
        
        // 设置搜索框值
        const searchInput = document.querySelector('.search-box input');
        if (searchInput && this.keyword) {
            searchInput.value = this.keyword;
        }
        
        this.bindEvents();
        this.loadCategories();
        this.loadArticles();
    },

    /**
     * 绑定事件
     */
    bindEvents() {
        // 分类筛选
        document.querySelectorAll('.category-list .category-tag').forEach(tag => {
            tag.addEventListener('click', (e) => {
                e.preventDefault();
                const href = e.currentTarget.getAttribute('href');
                const match = href.match(/category=(\d+)/);
                this.categoryId = match ? parseInt(match[1]) : 0;
                this.currentPage = 1;
                
                // 更新激活状态
                document.querySelectorAll('.category-list .category-tag').forEach(t => t.classList.remove('active'));
                e.currentTarget.classList.add('active');
                
                this.loadArticles();
            });
        });

        // 搜索功能
        const searchInput = document.querySelector('.search-box input');
        const searchBtn = document.querySelector('.search-box button');
        
        if (searchInput) {
            searchInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    this.keyword = e.target.value.trim();
                    this.currentPage = 1;
                    this.loadArticles();
                }
            });
        }
        
        if (searchBtn) {
            searchBtn.addEventListener('click', () => {
                const input = document.querySelector('.search-box input');
                if (input) {
                    this.keyword = input.value.trim();
                    this.currentPage = 1;
                    this.loadArticles();
                }
            });
        }

        // 分页点击事件委托
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('page-link')) {
                e.preventDefault();
                const page = parseInt(e.target.dataset.page);
                if (page && page !== this.currentPage) {
                    this.currentPage = page;
                    this.loadArticles();
                    // 滚动到列表顶部
                    document.getElementById('articleList')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }
        });
    },

    /**
     * 加载文章列表
     */
    async loadArticles() {
        if (this.isLoading) return;
        
        this.isLoading = true;
        const articleList = document.getElementById('articleList');
        const paginationContainer = document.querySelector('.pagination-container') || document.querySelector('.pagination');
        
        // 显示加载状态
        if (articleList) {
            articleList.innerHTML = '<div class="loading-state"><div class="spinner"></div><p>正在加载...</p></div>';
        }

        try {
            const params = new URLSearchParams({
                action: 'list',
                page: this.currentPage,
                pageSize: this.pageSize
            });
            
            if (this.categoryId > 0) {
                params.append('category_id', this.categoryId);
            }
            if (this.keyword) {
                params.append('keyword', this.keyword);
            }

            const response = await fetch(`php/article_api.php?${params.toString()}`);
            const result = await response.json();

            if (result.success) {
                this.renderArticles(result.data.articles);
                this.totalPages = result.data.pagination.total_pages;
                
                // 渲染分页
                if (paginationContainer) {
                    paginationContainer.outerHTML = result.data.pagination_html;
                }
                
                // 更新URL参数
                this.updateUrlParams();
            } else {
                this.showError(result.message || '加载失败');
            }
        } catch (error) {
            console.error('加载文章失败:', error);
            this.showError('网络错误，请稍后重试');
        } finally {
            this.isLoading = false;
        }
    },

    /**
     * 渲染文章列表
     */
    renderArticles(articles) {
        const articleList = document.getElementById('articleList');
        if (!articleList) return;

        if (!articles || articles.length === 0) {
            articleList.innerHTML = `
                <div class="empty-state">
                    <div class="empty-icon">📄</div>
                    <h3>暂无文章</h3>
                    <p>该分类下还没有文章，来发布第一篇吧！</p>
                    <a href="publish.html" class="btn btn-primary">发布文章</a>
                </div>
            `;
            return;
        }

        const html = articles.map(article => `
            <div class="article-card">
                <img src="${this.escapeHtml(article.cover_image)}" alt="文章封面" class="article-cover" onerror="this.src='img/article-thumb.jpg'">
                <div class="article-content">
                    <a href="article-detail.html?id=${article.article_id}" class="article-title">${this.escapeHtml(article.title)}</a>
                    <p class="article-summary">${this.escapeHtml(article.summary)}</p>
                    <div class="article-meta">
                        <span class="category">${this.escapeHtml(article.category_name || '未分类')}</span>
                        <span>👤 ${this.escapeHtml(article.author_name || '匿名')}</span>
                        <span>📅 ${article.created_at}</span>
                        <span>👁 ${article.view_count}</span>
                        <span>💬 ${article.comment_count}</span>
                    </div>
                </div>
            </div>
        `).join('');

        articleList.innerHTML = html;
    },

    /**
     * 加载分类列表
     */
    async loadCategories() {
        try {
            const response = await fetch('php/article_api.php?action=categories');
            const result = await response.json();

            if (result.success && result.data.categories) {
                this.renderCategories(result.data.categories);
            }
        } catch (error) {
            console.error('加载分类失败:', error);
        }
    },

    /**
     * 渲染分类列表
     */
    renderCategories(categories) {
        const categoryList = document.querySelector('.category-list');
        if (!categoryList) return;

        const currentCategoryId = this.getUrlParam('category') || 0;

        let html = `<a href="articles.html" class="category-tag ${currentCategoryId == 0 ? 'active' : ''}">全部</a>`;
        
        categories.forEach(cat => {
            html += `<a href="articles.html?category=${cat.category_id}" class="category-tag ${currentCategoryId == cat.category_id ? 'active' : ''}">${this.escapeHtml(cat.category_name)}</a>`;
        });

        categoryList.innerHTML = html;
        
        // 重新绑定事件
        this.bindEvents();
    },

    /**
     * 更新URL参数
     */
    updateUrlParams() {
        const params = new URLSearchParams();
        
        if (this.categoryId > 0) {
            params.set('category', this.categoryId);
        }
        if (this.currentPage > 1) {
            params.set('page', this.currentPage);
        }
        if (this.keyword) {
            params.set('keyword', this.keyword);
        }

        const newUrl = params.toString() 
            ? `articles.html?${params.toString()}` 
            : 'articles.html';
        
        window.history.replaceState({}, '', newUrl);
    },

    /**
     * 获取URL参数
     */
    getUrlParam(name) {
        const params = new URLSearchParams(window.location.search);
        return params.get(name);
    },

    /**
     * 显示错误信息
     */
    showError(message) {
        const articleList = document.getElementById('articleList');
        if (articleList) {
            // 检查是否需要初始化数据库
            const needInit = message.includes('数据库') || message.includes('表不存在');
            articleList.innerHTML = `
                <div class="error-state">
                    <div class="error-icon">⚠️</div>
                    <h3>加载失败</h3>
                    <p>${message}</p>
                    ${needInit ? '<a href="init.html" class="btn btn-primary" style="margin-top: 10px;">初始化数据库</a>' : '<button class="btn btn-primary" onclick="ArticleManager.loadArticles()">重新加载</button>'}
                </div>
            `;
        }
    },

    /**
     * HTML转义
     */
    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};

// 发布文章相关功能
const PublishManager = {
    /**
     * 初始化发布页面
     */
    init() {
        this.bindEvents();
        this.loadCategories();
    },

    /**
     * 绑定事件
     */
    bindEvents() {
        const form = document.getElementById('publishForm');
        if (form) {
            form.addEventListener('submit', (e) => this.handleSubmit(e));
        }
    },

    /**
     * 加载分类选项
     */
    async loadCategories() {
        try {
            const response = await fetch('php/article_api.php?action=categories');
            const result = await response.json();

            if (result.success && result.data.categories) {
                const select = document.querySelector('select[name="category_id"]');
                if (select) {
                    let html = '<option value="">请选择分类</option>';
                    result.data.categories.forEach(cat => {
                        html += `<option value="${cat.category_id}">${cat.category_name}</option>`;
                    });
                    select.innerHTML = html;
                }
            }
        } catch (error) {
            console.error('加载分类失败:', error);
        }
    },

    /**
     * 处理表单提交
     */
    async handleSubmit(e) {
        e.preventDefault();
        
        const form = e.target;
        const submitBtn = form.querySelector('#submitBtn');
        const originalText = submitBtn?.textContent;
        
        // 禁用提交按钮
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = '发布中...';
        }

        try {
            const formData = new FormData(form);
            
            const response = await fetch('php/article.php?action=create', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                alert('发布成功！');
                window.location.href = 'articles.html';
            } else {
                // 检查是否需要初始化
                if (result.message && (result.message.includes('数据库') || result.message.includes('初始化'))) {
                    if (confirm(result.message + '\n\n是否前往初始化页面？')) {
                        window.location.href = 'init.html';
                    }
                } else {
                    alert(result.message || '发布失败');
                }
            }
        } catch (error) {
            console.error('发布失败:', error);
            alert('网络错误，请稍后重试');
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        }
    }
};

// 文章详情相关功能
const ArticleDetailManager = {
    articleId: null,
    currentCommentPage: 1,
    commentPageSize: 10,

    /**
     * 初始化详情页面
     */
    init() {
        this.articleId = new URLSearchParams(window.location.search).get('id');
        if (this.articleId) {
            this.loadArticleDetail();
            this.loadComments();
            this.bindCommentEvents();
        }
    },

    /**
     * 绑定评论相关事件
     */
    bindCommentEvents() {
        // 评论表单提交
        const commentForm = document.getElementById('commentForm');
        if (commentForm) {
            commentForm.addEventListener('submit', (e) => this.submitComment(e));
        }
        
        // 登录后发表评论提示
        const commentLoginBtn = document.getElementById('commentLoginBtn');
        if (commentLoginBtn) {
            commentLoginBtn.addEventListener('click', () => {
                window.location.href = 'login.html?redirect=' + encodeURIComponent(window.location.href);
            });
        }
    },

    /**
     * 加载文章详情
     */
    async loadArticleDetail() {
        try {
            const response = await fetch(`php/article_api.php?action=detail&id=${this.articleId}`);
            const result = await response.json();

            if (result.success) {
                this.renderArticle(result.data.article);
                this.renderRelated(result.data.related);
            } else {
                this.showError(result.message || '文章不存在');
            }
        } catch (error) {
            console.error('加载文章详情失败:', error);
            this.showError('加载失败，请稍后重试');
        }
    },

    /**
     * 加载评论列表
     */
    async loadComments(page = 1) {
        this.currentCommentPage = page;
        
        try {
            const response = await fetch(`php/article_api.php?action=get_comments&article_id=${this.articleId}&page=${page}&pageSize=${this.commentPageSize}`);
            const result = await response.json();

            if (result.success) {
                this.renderComments(result.data.comments, result.data.pagination);
            } else {
                this.renderComments([], { total_count: 0 });
            }
        } catch (error) {
            console.error('加载评论失败:', error);
            this.renderComments([], { total_count: 0 });
        }
    },

    /**
     * 渲染评论列表
     */
    renderComments(comments, pagination) {
        const commentList = document.getElementById('commentList');
        const commentCount = document.getElementById('commentCount');
        
        if (commentCount) {
            commentCount.textContent = pagination.total_count || 0;
        }
        
        if (!commentList) return;
        
        if (!comments || comments.length === 0) {
            commentList.innerHTML = `
                <div class="empty-comments" style="text-align: center; padding: 40px; color: #7f8c8d;">
                    <p>暂无评论，快来发表第一条评论吧！</p>
                </div>
            `;
            return;
        }
        
        const html = comments.map(comment => `
            <div class="comment-item" style="display: flex; gap: 15px; padding: 20px 0; border-bottom: 1px solid #e1e8ed;">
                <img src="${comment.avatar}" alt="头像" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                <div style="flex: 1;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <span style="font-weight: 500; color: #2c3e50;">${comment.nickname || '匿名用户'}</span>
                        <span style="font-size: 12px; color: #95a5a6;">${comment.created_at}</span>
                    </div>
                    <p style="color: #2c3e50; line-height: 1.6; margin-bottom: 10px;">${this.escapeHtml(comment.content)}</p>
                </div>
            </div>
        `).join('');
        
        commentList.innerHTML = html;
        
        // 渲染分页
        this.renderCommentPagination(pagination);
    },

    /**
     * 渲染评论分页
     */
    renderCommentPagination(pagination) {
        const container = document.getElementById('commentPagination');
        if (!container || pagination.total_pages <= 1) {
            if (container) container.innerHTML = '';
            return;
        }
        
        let html = '';
        const currentPage = pagination.current_page;
        const totalPages = pagination.total_pages;
        
        // 上一页
        if (currentPage > 1) {
            html += `<a href="javascript:void(0)" onclick="ArticleDetailManager.loadComments(${currentPage - 1})" style="display: inline-block; padding: 8px 12px; margin: 0 5px; border: 1px solid #e1e8ed; border-radius: 4px; color: #2c3e50; text-decoration: none;">上一页</a>`;
        }
        
        // 页码
        const startPage = Math.max(1, currentPage - 2);
        const endPage = Math.min(totalPages, currentPage + 2);
        
        if (startPage > 1) {
            html += `<a href="javascript:void(0)" onclick="ArticleDetailManager.loadComments(1)" style="display: inline-block; padding: 8px 12px; margin: 0 5px; border: 1px solid #e1e8ed; border-radius: 4px; color: #2c3e50; text-decoration: none;">1</a>`;
            if (startPage > 2) html += `<span style="padding: 8px 12px;">...</span>`;
        }
        
        for (let i = startPage; i <= endPage; i++) {
            if (i === currentPage) {
                html += `<span style="display: inline-block; padding: 8px 12px; margin: 0 5px; background: #1abc9c; color: white; border-radius: 4px;">${i}</span>`;
            } else {
                html += `<a href="javascript:void(0)" onclick="ArticleDetailManager.loadComments(${i})" style="display: inline-block; padding: 8px 12px; margin: 0 5px; border: 1px solid #e1e8ed; border-radius: 4px; color: #2c3e50; text-decoration: none;">${i}</a>`;
            }
        }
        
        if (endPage < totalPages) {
            if (endPage < totalPages - 1) html += `<span style="padding: 8px 12px;">...</span>`;
            html += `<a href="javascript:void(0)" onclick="ArticleDetailManager.loadComments(${totalPages})" style="display: inline-block; padding: 8px 12px; margin: 0 5px; border: 1px solid #e1e8ed; border-radius: 4px; color: #2c3e50; text-decoration: none;">${totalPages}</a>`;
        }
        
        // 下一页
        if (currentPage < totalPages) {
            html += `<a href="javascript:void(0)" onclick="ArticleDetailManager.loadComments(${currentPage + 1})" style="display: inline-block; padding: 8px 12px; margin: 0 5px; border: 1px solid #e1e8ed; border-radius: 4px; color: #2c3e50; text-decoration: none;">下一页</a>`;
        }
        
        container.innerHTML = html;
    },

    /**
     * 提交评论
     */
    async submitComment(e) {
        e.preventDefault();
        
        // 检查是否登录
        if (!Auth.isLoggedIn()) {
            if (typeof showMessage === 'function') {
                showMessage('请先登录后再评论', 'warning');
            } else {
                alert('请先登录后再评论');
            }
            return;
        }
        
        const contentInput = document.getElementById('commentContent');
        const submitBtn = document.getElementById('commentSubmitBtn');
        const content = contentInput.value.trim();
        
        if (!content) {
            if (typeof showMessage === 'function') {
                showMessage('请输入评论内容', 'error');
            } else {
                alert('请输入评论内容');
            }
            return;
        }
        
        if (content.length > 1000) {
            if (typeof showMessage === 'function') {
                showMessage('评论内容不能超过1000字', 'error');
            } else {
                alert('评论内容不能超过1000字');
            }
            return;
        }
        
        // 禁用提交按钮
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = '提交中...';
        }
        
        try {
            const formData = new FormData();
            formData.append('article_id', this.articleId);
            formData.append('content', content);
            
            const response = await fetch('php/article_api.php?action=add_comment', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });
            
            const result = await response.json();
            
            if (result.success) {
                // 清空输入框
                contentInput.value = '';
                
                // 重新加载评论列表
                this.loadComments(1);
                
                // 更新文章评论数显示
                const commentCountEl = document.getElementById('articleCommentCount');
                if (commentCountEl) {
                    const currentCount = parseInt(commentCountEl.textContent) || 0;
                    commentCountEl.textContent = currentCount + 1;
                }
                
                if (typeof showMessage === 'function') {
                    showMessage('评论成功', 'success');
                }
            } else {
                if (typeof showMessage === 'function') {
                    showMessage(result.message || '评论失败', 'error');
                } else {
                    alert(result.message || '评论失败');
                }
            }
        } catch (error) {
            console.error('提交评论失败:', error);
            if (typeof showMessage === 'function') {
                showMessage('网络错误，请稍后重试', 'error');
            } else {
                alert('网络错误，请稍后重试');
            }
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = '发表评论';
            }
        }
    },

    /**
     * 渲染文章内容
     */
    renderArticle(article) {
        // 更新页面标题
        document.title = `${article.title} - 源泉`;
        
        // 更新文章内容
        const titleEl = document.querySelector('.article-title-detail');
        if (titleEl) titleEl.textContent = article.title;
        
        const categoryEl = document.querySelector('.article-header .category');
        if (categoryEl) categoryEl.textContent = article.category_name || '未分类';
        
        // 更新作者信息 - 文章头部
        const authorNameEl = document.querySelector('.article-meta-detail .author p:first-child');
        if (authorNameEl) {
            authorNameEl.innerHTML = `<a href="author.html?id=${article.author_id}" style="color: #1abc9c; text-decoration: none;">${article.author_name || '匿名'}</a>`;
        }
        
        const authorAvatarEl = document.querySelector('.article-meta-detail .author img');
        if (authorAvatarEl) {
            authorAvatarEl.src = article.author_avatar || 'img/avatar-default.jpg';
            authorAvatarEl.style.cursor = 'pointer';
            authorAvatarEl.onclick = () => window.location.href = `author.html?id=${article.author_id}`;
        }
        
        // 更新统计信息
        const viewCountEl = document.getElementById('articleViewCount');
        if (viewCountEl) viewCountEl.textContent = article.view_count || 0;
        
        const commentCountEl = document.getElementById('articleCommentCount');
        if (commentCountEl) commentCountEl.textContent = article.comment_count || 0;
        
        // 更新发布时间
        const publishTimeEl = document.querySelector('.article-meta-detail .author p:last-child');
        if (publishTimeEl) publishTimeEl.textContent = `发布于 ${article.created_at}`;
        
        // 更新文章内容
        const contentEl = document.querySelector('.article-content-detail');
        if (contentEl && article.content) {
            // 保留操作按钮，只替换内容部分
            const actionsEl = contentEl.querySelector('.article-actions');
            contentEl.innerHTML = article.content;
            if (actionsEl) contentEl.appendChild(actionsEl);
        }
        
        // 更新侧边栏作者信息
        const sidebarAvatar = document.getElementById('sidebarAuthorAvatar');
        if (sidebarAvatar) {
            sidebarAvatar.src = article.author_avatar || 'img/avatar-default.jpg';
        }
        
        const sidebarName = document.getElementById('sidebarAuthorName');
        if (sidebarName) {
            sidebarName.textContent = article.author_name || '匿名';
        }
        
        const sidebarBio = document.getElementById('sidebarAuthorBio');
        if (sidebarBio) {
            sidebarBio.textContent = article.author_bio || '暂无简介';
        }
        
        // 更新侧边栏作者链接
        const authorProfileLink = document.getElementById('authorProfileLink');
        const authorProfileLink2 = document.getElementById('authorProfileLink2');
        if (authorProfileLink) {
            authorProfileLink.href = `author.html?id=${article.author_id}`;
        }
        if (authorProfileLink2) {
            authorProfileLink2.href = `author.html?id=${article.author_id}`;
        }
        
        // 保存作者ID用于加好友
        this.currentAuthorId = article.author_id;
    },

    /**
     * 渲染相关文章
     */
    renderRelated(articles) {
        const container = document.querySelector('.related-articles');
        if (!container || !articles || articles.length === 0) return;
        
        const html = articles.map(article => `
            <div class="related-item">
                <a href="article-detail.html?id=${article.article_id}">${article.title}</a>
                <span>👁 ${article.view_count}</span>
            </div>
        `).join('');
        
        container.innerHTML = html;
    },

    /**
     * 显示错误
     */
    showError(message) {
        const container = document.querySelector('.article-container') || document.querySelector('main');
        if (container) {
            container.innerHTML = `
                <div class="error-state">
                    <div class="error-icon">⚠️</div>
                    <h3>${message}</h3>
                    <a href="articles.html" class="btn btn-primary">返回文章列表</a>
                </div>
            `;
        }
    },

    /**
     * HTML转义
     */
    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};

// 页面加载完成后初始化
document.addEventListener('DOMContentLoaded', function() {
    // 根据页面类型初始化不同功能
    if (document.getElementById('articleList')) {
        ArticleManager.init();
    }
    
    if (document.getElementById('publishForm')) {
        PublishManager.init();
    }
    
    if (document.querySelector('.article-detail-page')) {
        ArticleDetailManager.init();
    }
});
