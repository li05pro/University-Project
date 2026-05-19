$(document).ready(function() {
				// 页面切换功能
				$('.page-link').on('click', function(e) {
					e.preventDefault();
					// 更新导航栏激活状态
					$('.nav-link').removeClass('active');
					$(this).addClass('active');
					// 切换页面内容
					var target = $(this).attr('href');
					$('.page-section').removeClass('active');
					$(target).addClass('active');
					// 如果是首页，重新启动轮播图
					if (target === '#home') {
						$('#homeCarousel').carousel('cycle');
					}
				});
				// 切换登录/注册表单
				$('.switch-to-register').on('click', function(e) {
					e.preventDefault();
					$('#login-tab').removeClass('active');
					$('#register-tab').addClass('active');
					$('#login-form').removeClass('show active');
					$('#register-form').addClass('show active');
				});
				$('.switch-to-login').on('click', function(e) {
					e.preventDefault();
					$('#register-tab').removeClass('active');
					$('#login-tab').addClass('active');
					$('#register-form').removeClass('show active');
					$('#login-form').addClass('show active');
				});
				// 限时秒杀倒计时
				function updateCountdown() {
					var countdownElement = $('#countdown-timer');
					var time = countdownElement.text().split(':');
					var hours = parseInt(time[0]);
					var minutes = parseInt(time[1]);
					var seconds = parseInt(time[2]);
					if (seconds > 0) {
						seconds--;
					} else {
						seconds = 59;
						if (minutes > 0) {
							minutes--;
						} else {
							minutes = 59;
							if (hours > 0) {
								hours--;
							} else {
								// 倒计时结束
								hours = 0;
								minutes = 0;
								seconds = 0;
								clearInterval(countdownInterval);
								countdownElement.text('已结束');
								return;
							}
						}
					}
					countdownElement.text(
						(hours < 10 ? '0' : '') + hours + ':' +
						(minutes < 10 ? '0' : '') + minutes + ':' +
						(seconds < 10 ? '0' : '') + seconds
					);
				}
				var countdownInterval = setInterval(updateCountdown, 1000);
				// 详情页放大镜效果
				var zoomLens = $('#zoom-lens');
				var zoomResult = $('#zoom-result');
				var productImage = $('#product-image');
				var zoomResultImage = $('#zoom-result-image');
				productImage.on('mousemove', function(e) {
					if (!zoomResult.is(':visible')) return;
					var container = $('#product-image-container');
					var containerOffset = container.offset();
					var x = e.pageX - containerOffset.left;
					var y = e.pageY - containerOffset.top;
					// 确保放大镜不超出图片边界
					var lensWidth = zoomLens.width();
					var lensHeight = zoomLens.height();
					x = Math.max(0, Math.min(x - lensWidth / 2, container.width() - lensWidth));
					y = Math.max(0, Math.min(y - lensHeight / 2, container.height() - lensHeight));
					zoomLens.css({
						left: x + 'px',
						top: y + 'px'
					});
					// 计算放大图的位置
					var resultWidth = zoomResult.width();
					var resultHeight = zoomResult.height();
					var bgX = (x / container.width()) * (zoomResultImage.width() - resultWidth);
					var bgY = (y / container.height()) * (zoomResultImage.height() - resultHeight);
					zoomResultImage.css({
						left: -bgX + 'px',
						top: -bgY + 'px'
					});
				});
				$('#product-image-container').on('mouseenter', function() {
					zoomLens.show();
					zoomResult.show();
				}).on('mouseleave', function() {
					zoomLens.hide();
					zoomResult.hide();
				});
				// 缩略图切换
				$('.img-thumbnail').on('click', function() {
					$('.img-thumbnail').removeClass('img-thumbnail-active');
					$(this).addClass('img-thumbnail-active');
					var largeSrc = $(this).data('large');
					var zoomSrc = $(this).data('zoom');
					productImage.attr('src', largeSrc);
					zoomResultImage.attr('src', zoomSrc);
				});
				// 商品数量加减
				$('#decrease-qty').on('click', function() {
					var qtyInput = $('#quantity');
					var currentVal = parseInt(qtyInput.val());
					if (currentVal > 1) {
						qtyInput.val(currentVal - 1);
					}
				});
				$('#increase-qty').on('click', function() {
					var qtyInput = $('#quantity');
					var currentVal = parseInt(qtyInput.val());
					if (currentVal < 10) {
						qtyInput.val(currentVal + 1);
					}
				});
				// 购物车数据
				var cart = JSON.parse(localStorage.getItem('cart')) || [];
				// 更新购物车显示
				function updateCartDisplay() {
					var cartCount = cart.reduce((total, item) => total + item.quantity, 0);
					$('#cart-count').text(cartCount);
					$('#cart-items-count').text(cartCount);
					var subtotal = cart.reduce((total, item) => total + (item.price * item.quantity), 0);
					var shipping = subtotal > 0 ? (subtotal > 500 ? 0 : 20) : 0;
					var discount = cart.length > 0 ? 50 : 0;
					var total = subtotal + shipping - discount;
					$('#subtotal').text(subtotal.toFixed(2));
					$('#shipping').text(shipping.toFixed(2));
					$('#discount').text(discount.toFixed(2));
					$('#total').text(total.toFixed(2));
					// 更新购物车商品列表
					var cartItemsContainer = $('#cart-items-container');
					var emptyCartMessage = $('#empty-cart-message');
					if (cart.length === 0) {
						emptyCartMessage.show();
						$('#checkout-btn').prop('disabled', true);
						return;
					}
					emptyCartMessage.hide();
					$('#checkout-btn').prop('disabled', false);
					var cartItemsHtml = '';
					cart.forEach(function(item, index) {
						cartItemsHtml += `
		                    <div class="cart-item">
		                        <div class="row align-items-center">
		                            <div class="col-md-2 col-4">
		                                <img src="${item.image}" class="img-fluid" alt="${item.name}">
		                            </div>
		                            <div class="col-md-4 col-8">
		                                <h6>${item.name}</h6>
		                                <p class="text-muted mb-0">${item.description || ''}</p>
		                            </div>
		                            <div class="col-md-2 col-6">
		                                <div class="input-group input-group-sm">
		                                    <div class="input-group-prepend">
		                                        <button class="btn btn-outline-secondary cart-decrease" data-index="${index}" type="button">-</button>
		                                    </div>
		                                    <input type="text" class="form-control text-center cart-quantity" value="${item.quantity}" readonly>
		                                    <div class="input-group-append">
		                                        <button class="btn btn-outline-secondary cart-increase" data-index="${index}" type="button">+</button>
		                                    </div>
		                                </div>
		                            </div>
		                            <div class="col-md-2 col-4">
		                                <p class="mb-0 text-danger">¥${item.price.toFixed(2)}</p>
		                            </div>
		                            <div class="col-md-2 col-2 text-right">
		                                <button class="btn btn-link text-danger cart-remove" data-index="${index}">
		                                    <i class="fas fa-trash"></i>
		                                </button>
		                            </div>
		                        </div>
		                    </div>
		                `;
					});
					cartItemsContainer.html(cartItemsHtml);
					// 保存购物车数据到本地存储
					localStorage.setItem('cart', JSON.stringify(cart));
				}
				// 添加商品到购物车
				function addToCart(productId, name, price, image, quantity = 1) {
					var existingItem = cart.find(item => item.id === productId);
					if (existingItem) {
						existingItem.quantity += quantity;
					} else {
						cart.push({
							id: productId,
							name: name,
							price: price,
							image: image,
							quantity: quantity
						});
					}
					updateCartDisplay();
				// 显示添加成功消息
					$('<div class="alert alert-success alert-dismissible fade show" style="position: fixed; top: 20px; right: 20px; z-index: 1050;">' +
						'<strong>成功!</strong> 商品已添加到购物车。' +
						'<button type="button" class="close" data-dismiss="alert">' +
						'<span>&times;</span></button></div>').appendTo('body').delay(3000).fadeOut();
				}
				// 首页秒杀按钮点击事
				$('.btn-seckill').on('click', function() {
					var productId = $(this).data('id');
					var productElement = $(this).closest('.seckill-item');
					var productName = productElement.find('h5').text();
					var productPrice = parseFloat(productElement.find('.seckill-price').text().replace('¥', ''));
					var productImage = productElement.find('img').attr('src');
					addToCart(productId, productName, productPrice, productImage, 1);
				});
				// 首页加入购物车按钮
				$('.btn-add-to-cart').on('click', function() {
					var productId = $(this).data('id');
					var productElement = $(this).closest('.card');
					var productName = productElement.find('.card-title').text();
					var productPrice = parseFloat(productElement.find('.font-weight-bold').text().replace('¥',
						''));
					var productImage = productElement.find('img').attr('src');
					addToCart(productId, productName, productPrice, productImage, 1);
				});
				// 详情页加入购物车
				$('#add-to-cart-detail').on('click', function() {
					var quantity = parseInt($('#quantity').val());
					addToCart(100, '专业级数码相机', 4599,
						'https://images.unsplash.com/photo-1526170375885-4d8ecf77b99f?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60',
						quantity);
				});
				// 购物车商品数量调整
				$(document).on('click', '.cart-decrease', function() {
					var index = $(this).data('index');
					if (cart[index].quantity > 1) {
						cart[index].quantity--;
						updateCartDisplay();
					}
				});
				$(document).on('click', '.cart-increase', function() {
					var index = $(this).data('index');
					cart[index].quantity++;
					updateCartDisplay();
				});
				// 购物车商品删除
				$(document).on('click', '.cart-remove', function() {
					var index = $(this).data('index');
					cart.splice(index, 1);
					updateCartDisplay();
				});
				// 表单验证和提交
				$('#loginForm').on('submit', function(e) {
					e.preventDefault();
					var email = $('#login-email').val();
					var password = $('#login-password').val();
					if (email && password) {
						alert('登录成功！欢迎回到源泉商城。');
						// 在实际应用中，这里会进行AJAX请求验证用户信息
					}
				});
				$('#registerForm').on('submit', function(e) {
					e.preventDefault();
					var password = $('#register-password').val();
					var confirmPassword = $('#register-confirm-password').val();
					if (password !== confirmPassword) {
						$('#register-confirm-password').addClass('is-invalid');
						return;
					}
					$('#register-confirm-password').removeClass('is-invalid');
					// 在实际应用中，这里会进行AJAX请求提交注册信息
					alert('注册成功！欢迎加入源泉商城。');
					// 切换到登录表单
					$('.switch-to-login').click();
				});
				// 初始化购物车显示
				updateCartDisplay();
				// 初始化轮播图
				$('#homeCarousel').carousel({
					interval: 3000
				});
			});
			
			
			
			
			
			