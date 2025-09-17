document.addEventListener('DOMContentLoaded', () => {
    class PlansManager {
        constructor() {
            this.container = document.querySelector('.plans-container');
            if (!this.container) return;

            this.currentTab = 'monthly';
            this.currentPage = 1;
            this.isLoading = false;
            this.cache = {
                monthly: null,
                annual: null
            };
            this.isDataLoaded = false;

            this.init();
        }

        init() {
            this.bindEvents();
            this.updatePaginationData();
            this.preloadAllData();
        }

        bindEvents() {
            // Tab switching and pagination
            this.container.addEventListener('click', (e) => {
                const tabBtn = e.target.closest('.plans-tab-button');
                if (tabBtn) {
                    e.preventDefault();
                    this.handleTabSwitch(tabBtn);
                    return;
                }

                const pageBtn = e.target.closest('.plans-page-btn:not(.active)');
                if (pageBtn) {
                    e.preventDefault();
                    const page = parseInt(pageBtn.dataset.page, 10);
                    this.loadPage(page);
                    return;
                }

                const prevNextBtn = e.target.closest('.plans-prev-btn, .plans-next-btn');
                if (prevNextBtn) {
                    e.preventDefault();
                    const page = parseInt(prevNextBtn.dataset.page, 10);
                    this.loadPage(page);
                    return;
                }
            });

            // Keyboard navigation
            this.container.addEventListener('keydown', (e) => {
                if ((e.key === 'Enter' || e.key === ' ') &&
                    (e.target.classList.contains('plans-tab-button') || e.target.classList.contains('plans-page-btn'))) {
                    e.preventDefault();
                    e.target.click();
                }
            });

            // Browser back/forward
            window.addEventListener('popstate', (e) => {
                if (e.state && e.state.plansTab) {
                    this.currentTab = e.state.plansTab;
                    this.currentPage = e.state.plansPage || 1;
                    this.displayCachedData();
                }
            });
        }

        preloadAllData() {
            if (!this.isCacheEnabled() || this.isDataLoaded) return;

            if (!this.cache.monthly && !this.cache.annual) {
                this.showLoading(false);
            }

            const data = new URLSearchParams({
                action: 'load_all_plans',
                nonce: plans_ajax.nonce,
                per_page: this.getPerPage(),
                limit: this.getLimit()
            });

            fetch(plans_ajax.ajax_url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: data
            })
                .then(res => res.json())
                .then(result => {
                    if (result.success) {
                        this.cache.monthly = result.data.monthly || {};
                        this.cache.annual = result.data.annual || {};
                        this.isDataLoaded = true;
                        this.displayCachedData(); // автоматично показуємо першу сторінку
                    } else {
                        this.showError(result.data?.message || plans_ajax.error);
                    }
                })
                .catch(() => this.showError(plans_ajax.error))
                .finally(() => this.hideLoading());
        }


        loadCacheFromStorage() {
            if (!this.isSessionStorageAvailable()) return false;

            try {
                const cached = sessionStorage.getItem('plans_cache');
                if (!cached) return false;

                const cacheData = JSON.parse(cached);
                const cacheAge = Date.now() - cacheData.timestamp;
                const maxAge = 30 * 60 * 1000;

                if (cacheAge > maxAge ||
                    cacheData.settings.perPage !== this.getPerPage() ||
                    cacheData.settings.limit !== this.getLimit()) {
                    sessionStorage.removeItem('plans_cache');
                    return false;
                }

                this.cache = cacheData.data;
                this.isDataLoaded = true;
                console.log('Plans data loaded from cache');
                return true;
            } catch (err) {
                console.error('Error loading cache from storage:', err);
                sessionStorage.removeItem('plans_cache');
                return false;
            }
        }

        handleTabSwitch(tabButton) {
            if (tabButton.classList.contains('active') || this.isLoading) return;

            const newTab = tabButton.dataset.tab;

            this.container.querySelectorAll('.plans-tab-button').forEach(btn => {
                btn.classList.remove('active');
                btn.setAttribute('aria-selected', 'false');
            });

            tabButton.classList.add('active');
            tabButton.setAttribute('aria-selected', 'true');

            this.currentTab = newTab;
            this.currentPage = 1;

            this.displayCachedData();
        }

        loadPage(page) {
            if (this.isLoading || page === this.currentPage) return;
            this.currentPage = page;
            this.displayCachedData();
        }

        displayCachedData() {
            if (!this.isDataLoaded || !this.cache[this.currentTab]) {
                if (!this.isDataLoaded) {
                    this.preloadAllData();
                }
                return;
            }

            const pageData = this.cache[this.currentTab][this.currentPage];
            if (!pageData) {
                this.showError('No data available for this page');
                return;
            }
            this.renderPlansFromCache(pageData);
        }


        renderPlansFromCache(pageData) {
            const content = this.container.querySelector('#plans-content');
            const paginationWrapper = this.container.querySelector('.plans-pagination-wrapper');

            if (content) {
                const plansHtml = this.renderPlanCards(pageData.plans);
                content.style.opacity = '0';
                setTimeout(() => {
                    content.innerHTML = plansHtml;
                    content.style.opacity = '1';
                }, 100);
            }

            if (paginationWrapper) {
                const paginationHtml = this.renderPagination(pageData.pagination);
                paginationWrapper.innerHTML = paginationHtml;
            }

            this.updatePaginationData();
        }

        renderPlanCards(plans) {
            if (!plans || plans.length === 0) {
                return '<p class="plans-empty-message">No plans available for this period.</p>';
            }

            let html = '<div class="plans-grid">';
            plans.forEach(plan => {
                const classes = ['plan-card'];
                if (plan.is_starred) classes.push('plan-card--starred');

                html += `<div class="${classes.join(' ')}">`;

                if (plan.is_starred) html += '<div class="plan-badge">Recommended</div>';

                html += `<h3 class="plan-title">${this.escapeHtml(plan.title)}</h3>`;
                html += `<div class="plan-price">${this.escapeHtml(plan.price)}</div>`;

                if (plan.features && plan.features.length > 0) {
                    html += '<ul class="plan-features">';
                    plan.features.forEach(feature => {
                        html += `<li class="plan-feature">${this.escapeHtml(feature)}</li>`;
                    });
                    html += '</ul>';
                }

                if (plan.button_text && plan.button_link) {
                    html += `<a href="${this.escapeHtml(plan.button_link)}" class="plan-button">${this.escapeHtml(plan.button_text)}</a>`;
                }

                html += '</div>';
            });

            html += '</div>';
            return html;
        }

        renderPagination(pagination) {
            if (!pagination || pagination.pages <= 1) return '';

            let html = `<div class="plans-pagination" data-current="${pagination.current_page}" data-total="${pagination.pages}" data-plan-type="${pagination.plan_type}">`;

            if (pagination.has_prev) html += `<button class="plans-page-btn plans-prev-btn" data-page="${pagination.current_page - 1}">← Previous</button>`;

            html += '<div class="plans-page-numbers">';
            const start = Math.max(1, pagination.current_page - 2);
            const end = Math.min(pagination.pages, pagination.current_page + 2);

            if (start > 1) {
                html += '<button class="plans-page-btn" data-page="1">1</button>';
                if (start > 2) html += '<span class="plans-page-dots">...</span>';
            }

            for (let i = start; i <= end; i++) {
                const active = i === pagination.current_page ? ' active' : '';
                html += `<button class="plans-page-btn${active}" data-page="${i}">${i}</button>`;
            }

            if (end < pagination.pages) {
                if (end < pagination.pages - 1) html += '<span class="plans-page-dots">...</span>';
                html += `<button class="plans-page-btn" data-page="${pagination.pages}">${pagination.pages}</button>`;
            }

            html += '</div>';
            if (pagination.has_next) html += `<button class="plans-page-btn plans-next-btn" data-page="${pagination.current_page + 1}">Next →</button>`;
            html += '</div>';

            const showing = Math.min(pagination.current_page * pagination.per_page, pagination.total);
            html += `<div class="plans-pagination-info">Showing ${showing} of ${pagination.total} plans</div>`;

            return html;
        }

        showLoading(dimContent = true) {
            this.isLoading = true;
            const loading = this.container.querySelector('.plans-loading');
            const content = this.container.querySelector('#plans-content');
            const pagination = this.container.querySelector('.plans-pagination-wrapper');

            if (loading) loading.style.display = 'block';
            if (dimContent) {
                if (content) content.style.opacity = '0.5';
                if (pagination) pagination.style.opacity = '0.5';
            }
        }

        hideLoading() {
            this.isLoading = false;
            const loading = this.container.querySelector('.plans-loading');
            const content = this.container.querySelector('#plans-content');
            const pagination = this.container.querySelector('.plans-pagination-wrapper');

            if (loading) loading.style.display = 'none';
            if (content) content.style.opacity = '1';
            if (pagination) pagination.style.opacity = '1';
        }

        showError(message) {
            const content = this.container.querySelector('#plans-content');
            if (!content) return;

            content.innerHTML = `
                <div class="plans-error">
                    <p class="plans-error-message">${message}</p>
                    <button class="plans-retry-btn">${plans_ajax.retry || 'Try Again'}</button>
                </div>
            `;

            const retryBtn = content.querySelector('.plans-retry-btn');
            if (retryBtn) {
                retryBtn.addEventListener('click', () => {
                    this.clearCache();
                    this.preloadAllData();
                });
            }
        }

        updatePaginationData() {
            const pagination = this.container.querySelector('.plans-pagination');
            if (pagination) {
                this.currentPage = parseInt(pagination.dataset.current, 10) || 1;
            }
        }

        getPerPage() {
            return parseInt(this.container.dataset.perPage, 10) || 6;
        }

        getLimit() {
            return parseInt(this.container.dataset.limit, 10) || -1;
        }

        isCacheEnabled() {
            return this.container.dataset.cacheEnabled !== 'false';
        }

        isSessionStorageAvailable() {
            try {
                const test = 'test';
                sessionStorage.setItem(test, test);
                sessionStorage.removeItem(test);
                return true;
            } catch (e) {
                return false;
            }
        }

        scrollToPlans() {
            const offset = this.container.getBoundingClientRect().top + window.scrollY - 100;
            window.scrollTo({ top: offset, behavior: 'smooth' });
        }

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        clearCache() {
            this.cache = { monthly: null, annual: null };
            this.isDataLoaded = false;
            if (this.isSessionStorageAvailable()) sessionStorage.removeItem('plans_cache');
        }

        refreshCache() {
            this.clearCache();
            return this.preloadAllData();
        }

        getCacheStats() {
            return {
                isLoaded: this.isDataLoaded,
                hasMonthly: !!this.cache.monthly,
                hasAnnual: !!this.cache.annual,
                monthlyPages: this.cache.monthly ? Object.keys(this.cache.monthly).length : 0,
                annualPages: this.cache.annual ? Object.keys(this.cache.annual).length : 0
            };
        }
    }

    // Utility functions
    const PlansUtils = {
        formatPrice(price, currency = '$') {
            if (typeof price === 'string') return price;
            return currency + parseFloat(price).toFixed(2);
        },

        debounce(func, wait) {
            let timeout;
            return function (...args) {
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(this, args), wait);
            };
        },

        showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `plans-notification plans-notification--${type}`;
            notification.innerHTML = message;

            document.body.appendChild(notification);

            setTimeout(() => {
                notification.style.transition = 'opacity 0.3s';
                notification.style.opacity = '0';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        },

        initializeCache() {
            if (window.plansManager && window.plansManager.loadCacheFromStorage()) {
                console.log('Plans cache initialized from storage');
                return true;
            }
            return false;
        }
    };

    // Initialize
    if (document.querySelector('.plans-container')) {
        const plansManager = new PlansManager();
        window.plansManager = plansManager;
        plansManager.loadCacheFromStorage();
    }

    window.PlansUtils = PlansUtils;

    // Developer events
    document.addEventListener('plans:refresh', () => {
        if (window.plansManager) window.plansManager.refreshCache();
    });

    document.addEventListener('plans:clearCache', () => {
        if (window.plansManager) window.plansManager.clearCache();
    });
});
