/* AI SEO Editor — Admin JavaScript */
/* globals AISeoConfig */

(function () {
	'use strict';

	const Config  = window.AISeoConfig || {};
	const i18n    = Config.i18n || {};
	const restUrl = Config.restUrl || '';
	const nonce   = Config.nonce || '';
	const githubNonce = Config.githubNonce || nonce;

	function initAutoPublisherEnhanced() {
		const saveBtn = document.getElementById('aiseo-ap-save');
		const triggerBtn = document.getElementById('aiseo-ap-trigger');
		const refreshBtn = document.getElementById('aiseo-ap-refresh-queue');
		const stopCronBtn = document.getElementById('aiseo-ap-stop-cron');
		const clearQueueBtn = document.getElementById('aiseo-ap-clear-queue-order');
		const rebuildQueueBtn = document.getElementById('aiseo-ap-rebuild-queue');
		const checkCronBtn = document.getElementById('aiseo-ap-check-cron-status');
		const peekNextBtn = document.getElementById('aiseo-ap-peek-next-post');
		const enabledEl = document.getElementById('aiseo-ap-enabled');
		const statusLbl = document.getElementById('aiseo-ap-status-label');
		const categorySelect = document.getElementById('aiseo-ap-categories');
		const categorySearch = document.getElementById('aiseo-ap-category-search');
		const categoryOptions = document.getElementById('aiseo-ap-category-options');
		const categoryChips = document.getElementById('aiseo-ap-category-chips');
		const categoryCount = document.getElementById('aiseo-ap-category-count');
		const clearCategoriesBtn = document.getElementById('aiseo-ap-clear-categories');
		const nextRunText = document.getElementById('aiseo-ap-next-run-text');
		const queueWrap = document.getElementById('aiseo-ap-queue-wrap');
		const drawer = document.getElementById('aiseo-ap-preview-drawer');
		const drawerTitle = document.getElementById('aiseo-ap-drawer-title');
		const drawerSeo = document.getElementById('aiseo-ap-drawer-seo');
		const drawerRead = document.getElementById('aiseo-ap-drawer-read');
		const drawerTraffic = document.getElementById('aiseo-ap-drawer-traffic');
		const drawerConfidence = document.getElementById('aiseo-ap-drawer-confidence');
		const drawerExcerpt = document.getElementById('aiseo-ap-drawer-excerpt');
		const drawerMeta = document.getElementById('aiseo-ap-drawer-meta');
		const drawerFaq = document.getElementById('aiseo-ap-drawer-faq');
		const drawerLinks = document.getElementById('aiseo-ap-drawer-links');
		const drawerEdit = document.getElementById('aiseo-ap-drawer-edit');
		const maintenanceCronStatus = document.getElementById('aiseo-ap-maintenance-cron-status');
		const maintenanceNextRun = document.getElementById('aiseo-ap-maintenance-next-run');
		const maintenanceQueueCount = document.getElementById('aiseo-ap-maintenance-queue-count');
		const maintenanceNextPost = document.getElementById('aiseo-ap-maintenance-next-post');
		const counterEls = document.querySelectorAll('[data-counter-target]');

		initTabs();
		initCategoryPicker();
		initProxyActions();
		initPreviewDrawer();
		animateCounters();
		bindQueueActions();

		if (enabledEl && statusLbl) {
			enabledEl.addEventListener('change', updateStatusLabel);
			updateStatusLabel();
		}

		if (saveBtn) {
			saveBtn.addEventListener('click', async () => {
				UI.loading(saveBtn, true);
				try {
					const res = await API.saveAutoPublisherSettings(getFormData());
					const data = res.data || {};
					updateStatusLabel();
					updateNextRunText(data.next_run);
					updateMaintenancePanel(data);
					UI.notice('aiseo-ap-notice', res.message || 'Ayarlar kaydedildi.', 'success');
				} catch (e) {
					UI.notice('aiseo-ap-notice', e.message || i18n.error, 'error');
				} finally {
					UI.loading(saveBtn, false);
				}
			});
		}

		if (triggerBtn) {
			triggerBtn.addEventListener('click', async () => {
				if (!confirm('Kuyruktan bir taslak simdi islenip yayinlansin mi? Bu islem birkac dakika surebilir.')) return;
				await runQueueTrigger(triggerBtn);
			});
		}

		if (refreshBtn) {
			refreshBtn.addEventListener('click', async () => {
				UI.loading(refreshBtn, true);
				try {
					const res = await API.refreshAutoPublisherQueue();
					const queue = res.data?.queue || [];
					const total = parseInt(res.data?.total || queue.length || 0, 10);
					renderQueue(queue);
					updateQueueKpi(total);
					updateMaintenancePanel(res.data || {});
					UI.notice('aiseo-ap-notice', res.message || 'Kuyruk sirasi guncellendi.', 'success');
				} catch (e) {
					UI.notice('aiseo-ap-notice', e.message || i18n.error, 'error');
				} finally {
					UI.loading(refreshBtn, false);
				}
			});
		}

		if (stopCronBtn) {
			stopCronBtn.addEventListener('click', async () => {
				await runMaintenanceAction(stopCronBtn, 'stop_cron');
			});
		}

		if (clearQueueBtn) {
			clearQueueBtn.addEventListener('click', async () => {
				if (!confirm('Sadece otomatik yayin sira kayitlari silinecek. Yazilar silinmeyecek. Devam edilsin mi?')) return;
				await runMaintenanceAction(clearQueueBtn, 'clear_queue');
			});
		}

		if (rebuildQueueBtn) {
			rebuildQueueBtn.addEventListener('click', async () => {
				await runMaintenanceAction(rebuildQueueBtn, 'rebuild_queue', { limit: 200 }, true);
			});
		}

		if (checkCronBtn) {
			checkCronBtn.addEventListener('click', async () => {
				await runMaintenanceAction(checkCronBtn, 'cron_status');
			});
		}

		if (peekNextBtn) {
			peekNextBtn.addEventListener('click', async () => {
				await runMaintenanceAction(peekNextBtn, 'peek_next');
			});
		}

		function getFormData() { const categoryEls = document.querySelectorAll('#aiseo-ap-categories option:checked'); return { enabled: document.getElementById('aiseo-ap-enabled')?.checked || false, interval_hours: parseFloat(document.getElementById('aiseo-ap-interval')?.value) || 24, min_seo_score: parseInt(document.getElementById('aiseo-ap-min-seo')?.value, 10) || 70, min_readability_score: parseInt(document.getElementById('aiseo-ap-min-read')?.value, 10) || 60, category_ids: Array.from(categoryEls).map((o) => parseInt(o.value, 10)).filter(Boolean), internal_links_count: parseInt(document.getElementById('aiseo-ap-links')?.value, 10) || 3, target_words: parseInt(document.getElementById('aiseo-ap-words')?.value, 10) || 1000, tone: document.getElementById('aiseo-ap-tone')?.value || 'professional', include_faq: document.getElementById('aiseo-ap-faq')?.checked || false, auto_generate: document.getElementById('aiseo-ap-auto-generate')?.checked || false, optimize_before_publish: document.getElementById('aiseo-ap-optimize')?.checked || false }; }
		function updateStatusLabel() { if (!enabledEl || !statusLbl) return; statusLbl.textContent = enabledEl.checked ? 'Aktif' : 'Pasif'; statusLbl.className = 'aiseo-ap-status-label ' + (enabledEl.checked ? 'active' : 'inactive'); }
		function updateNextRunText(nextRun) { if (nextRunText) nextRunText.textContent = nextRun ? 'Sonraki calisma: ' + nextRun : 'Henuz zamanlanmamis.'; }
		function updateMaintenancePanel(data) { const cronStatus = data.cron_status || {}; const nextRun = typeof cronStatus.next_run === 'string' && cronStatus.next_run ? cronStatus.next_run : (data.next_run || 'Planli cron yok.'); const queueTotal = Number.isFinite(parseInt(data.queue_total ?? data.total ?? 0, 10)) ? Math.max(0, parseInt(data.queue_total ?? data.total ?? 0, 10)) : 0; const nextPost = data.next_post || null; if (maintenanceCronStatus) maintenanceCronStatus.textContent = cronStatus.is_scheduled ? 'Aktif' : 'Cron kapali'; if (maintenanceNextRun) maintenanceNextRun.textContent = nextRun; if (maintenanceQueueCount) maintenanceQueueCount.textContent = String(queueTotal); if (maintenanceNextPost) maintenanceNextPost.textContent = nextPost && nextPost.id ? '#' + nextPost.id + ' - ' + (nextPost.title || 'Basliksiz taslak') : 'Kuyrukta bekleyen yazi yok.'; updateNextRunText(cronStatus.next_run || data.next_run || null); updateQueueKpi(queueTotal); }
		async function runMaintenanceAction(buttonEl, action, extraBody, shouldRefreshQueue) { UI.loading(buttonEl, true); try { const res = await API.maintainAutoPublisher(Object.assign({ action: action }, extraBody || {})); const data = res.data || {}; updateMaintenancePanel(data); if (Array.isArray(data.queue)) renderQueue(data.queue); else if (shouldRefreshQueue) { const queueRes = await API.getAutoPublisherQueue(); renderQueue(queueRes.data?.queue || []); updateMaintenancePanel(queueRes.data || {}); } UI.notice('aiseo-ap-notice', res.message || 'Islem tamamlandi.', 'success'); } catch (e) { UI.notice('aiseo-ap-notice', e.message || i18n.error, 'error'); } finally { UI.loading(buttonEl, false); } }
		function initTabs() { document.querySelectorAll('.aiseo-ap-tab').forEach((tab) => { tab.addEventListener('click', () => { const key = tab.dataset.apTab; document.querySelectorAll('.aiseo-ap-tab').forEach((item) => { const active = item === tab; item.classList.toggle('is-active', active); item.setAttribute('aria-selected', active ? 'true' : 'false'); }); document.querySelectorAll('.aiseo-ap-tab-panel').forEach((panel) => { const active = panel.dataset.apPanel === key; panel.classList.toggle('is-active', active); panel.hidden = !active; }); }); }); }
		function initCategoryPicker() { if (!categorySelect || !categoryOptions || !categoryChips) return; categoryOptions.querySelectorAll('.aiseo-ap-category-option').forEach((btn) => { btn.addEventListener('click', () => toggleCategory(parseInt(btn.dataset.termId, 10))); }); if (categorySearch) { categorySearch.addEventListener('input', () => { const query = String(categorySearch.value || '').trim().toLowerCase(); categoryOptions.querySelectorAll('.aiseo-ap-category-option').forEach((btn) => { const name = String(btn.dataset.termName || '').toLowerCase(); btn.hidden = query ? !name.includes(query) : false; }); }); } if (clearCategoriesBtn) { clearCategoriesBtn.addEventListener('click', () => { Array.from(categorySelect.options).forEach((option) => { option.selected = false; }); renderCategorySelection(); }); } renderCategorySelection(); }
		function toggleCategory(termId) { const option = Array.from(categorySelect.options).find((item) => parseInt(item.value, 10) === termId); if (!option) return; option.selected = !option.selected; renderCategorySelection(); }
		function renderCategorySelection() { if (!categorySelect || !categoryOptions || !categoryChips) return; const selected = Array.from(categorySelect.options).filter((option) => option.selected); if (categoryCount) categoryCount.textContent = String(selected.length); categoryOptions.querySelectorAll('.aiseo-ap-category-option').forEach((btn) => { const termId = parseInt(btn.dataset.termId, 10); const match = selected.some((option) => parseInt(option.value, 10) === termId); btn.classList.toggle('is-selected', match); }); if (!selected.length) { categoryChips.innerHTML = '<span class="aiseo-ap-row-meta">Tum kategoriler dahil.</span>'; return; } categoryChips.innerHTML = selected.map((option) => { const termId = parseInt(option.value, 10); const label = option.textContent.replace(/\s*\(\d+\)\s*$/, ''); return '<span class="aiseo-ap-chip">' + escapeHtml(label) + '<button type="button" data-chip-remove="' + termId + '" aria-label="Kategoriyi kaldir">x</button></span>'; }).join(''); categoryChips.querySelectorAll('[data-chip-remove]').forEach((btn) => { btn.addEventListener('click', () => toggleCategory(parseInt(btn.dataset.chipRemove, 10))); }); }
		function initProxyActions() { document.querySelectorAll('.aiseo-ap-proxy-action').forEach((btn) => { btn.addEventListener('click', () => { const target = document.getElementById(btn.dataset.target || ''); if (target) target.click(); }); }); }
		function initPreviewDrawer() { if (!drawer) return; drawer.querySelectorAll('[data-ap-drawer-close]').forEach((el) => { el.addEventListener('click', closeDrawer); }); document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && !drawer.hidden) closeDrawer(); }); }
		function openDrawer(payload) { if (!drawer || !payload) return; if (drawerTitle) drawerTitle.textContent = payload.title || 'Taslak onizlemesi'; if (drawerSeo) drawerSeo.textContent = payload.seoScore > 0 ? String(payload.seoScore) : '--'; if (drawerRead) drawerRead.textContent = payload.readScore > 0 ? String(payload.readScore) : '--'; if (drawerTraffic) drawerTraffic.textContent = payload.traffic ? '~' + payload.traffic : '--'; if (drawerConfidence) drawerConfidence.textContent = payload.confidence || '--'; if (drawerExcerpt) drawerExcerpt.textContent = payload.excerpt || 'Icerik onizlemesi bulunamadi.'; if (drawerMeta) drawerMeta.textContent = payload.meta || 'Meta description bulunamadi.'; if (drawerFaq) drawerFaq.textContent = payload.faqCount ? payload.faqCount + ' potansiyel soru basligi tespit edildi.' : 'FAQ bolumu henuz gorunmuyor.'; if (drawerLinks) { const links = Array.isArray(payload.internalLinks) ? payload.internalLinks : []; drawerLinks.innerHTML = links.length ? links.map((item) => '<li>' + escapeHtml(item) + '</li>').join('') : '<li>Ic link onizlemesi henuz yok.</li>'; } if (drawerEdit) drawerEdit.href = payload.editUrl || '#'; drawer.hidden = false; drawer.setAttribute('aria-hidden', 'false'); document.body.classList.add('aiseo-ap-drawer-open'); }
		function closeDrawer() { if (!drawer) return; drawer.hidden = true; drawer.setAttribute('aria-hidden', 'true'); document.body.classList.remove('aiseo-ap-drawer-open'); }
		function animateCounters() { counterEls.forEach((el) => { const rawTarget = String(el.dataset.counterTarget || ''); if (!rawTarget || !/^\d+$/.test(rawTarget)) return; const target = parseInt(rawTarget, 10); if (!Number.isFinite(target)) return; const original = String(el.textContent || ''); const hasScale = /\/100$/.test(original); const hasApprox = /^~/.test(original); const start = performance.now(); const duration = 700; function frame(now) { const progress = Math.min(1, (now - start) / duration); const value = Math.round(target * progress); el.textContent = (hasApprox ? '~' : '') + value.toLocaleString('tr-TR') + (hasScale ? '/100' : ''); if (progress < 1) window.requestAnimationFrame(frame); } window.requestAnimationFrame(frame); }); }
		function bindQueueActions() { bindPreviewButtons(); bindSkipButtons(); bindRegenerateButtons(); bindPublishButtons(); }
		function bindPreviewButtons() { document.querySelectorAll('.aiseo-ap-preview-btn').forEach((btn) => { if (btn._boundPreview) return; btn._boundPreview = true; btn.addEventListener('click', () => { try { openDrawer(JSON.parse(btn.dataset.preview || '{}')); } catch (e) { openDrawer({ title: 'Onizleme hazir degil', excerpt: 'Icerik onizleme verisi okunamadi.' }); } }); }); }
		function bindSkipButtons() { document.querySelectorAll('.aiseo-ap-skip-btn').forEach((btn) => { if (btn._boundSkip) return; btn._boundSkip = true; btn.addEventListener('click', async () => { const postId = btn.dataset.postId; UI.loading(btn, true); try { await API.skipAutoPublisherPost(postId, true); const row = btn.closest('tr'); if (row) row.remove(); if (!document.querySelector('#aiseo-ap-queue-body tr')) renderQueue([]); UI.notice('aiseo-ap-notice', 'Yazi kuyruktan cikarildi.', 'success'); } catch (e) { UI.notice('aiseo-ap-notice', e.message || i18n.error, 'error'); } finally { UI.loading(btn, false); } }); }); }
		function bindRegenerateButtons() { document.querySelectorAll('.aiseo-ap-regenerate-btn').forEach((btn) => { if (btn._boundRegen) return; btn._boundRegen = true; btn.addEventListener('click', async () => { const postId = btn.dataset.postId; if (!postId) return; UI.loading(btn, true); try { await API.regeneratePost(postId); UI.notice('aiseo-ap-notice', 'Icerik yeniden uretim icin kuyruga alindi.', 'success'); } catch (e) { UI.notice('aiseo-ap-notice', e.message || i18n.error, 'error'); } finally { UI.loading(btn, false); } }); }); }
		function bindPublishButtons() { document.querySelectorAll('.aiseo-ap-publish-btn').forEach((btn) => { if (btn._boundPublish) return; btn._boundPublish = true; btn.addEventListener('click', async () => { if (btn.disabled) return; if (!confirm('Siradaki taslagi hemen yayinlamak istiyor musunuz?')) return; await runQueueTrigger(btn); }); }); }
		async function runQueueTrigger(buttonEl) { UI.loading(buttonEl, true); UI.notice('aiseo-ap-notice', 'Isleniyor, lutfen bekleyin...', 'info'); try { const postId = parseInt(buttonEl?.dataset?.postId || 0, 10); const res = await API.triggerAutoPublisher(postId); const data = res.data || {}; const msg = res.message || 'Tamamlandi.'; const suffix = data.seo_score ? ' (SEO: ' + data.seo_score + ', Okunabilirlik: ' + data.readability_score + ')' : ''; UI.notice('aiseo-ap-notice', msg + suffix, 'success'); setTimeout(() => window.location.reload(), 1800); } catch (e) { UI.notice('aiseo-ap-notice', e.message || i18n.error, 'error'); } finally { UI.loading(buttonEl, false); } }
		function scoreTone(score) { if (score >= 80) return 'good'; if (score >= 60) return 'warn'; if (score > 0) return 'bad'; return 'idle'; }
		function estimateTraffic(item) { const seo = parseInt(item.seo_score || 0, 10); const read = parseInt(item.read_score || 0, 10); if (seo <= 0 && read <= 0) return 0; return Math.max(15, Math.round((Math.max(seo, 0) * 0.9) + (Math.max(read, 0) * 0.55))); }
		function estimateKeywordVolume(item) { const title = String(item.title || '').trim(); if (!title) return 0; const wordCount = Math.max(1, title.split(/\\s+/).length); return Math.min(9800, Math.round((title.length * 28) + (wordCount * 160))); }
		function estimateConfidence(item) { if (item.score_fail) return { label: 'Dusuk', tone: 'bad' }; if (parseInt(item.seo_score || 0, 10) >= 80 && parseInt(item.read_score || 0, 10) >= 70) return { label: 'Yuksek', tone: 'good' }; if (parseInt(item.attempts || 0, 10) > 0) return { label: 'Orta', tone: 'warn' }; return { label: 'Hazirlaniyor', tone: 'idle' }; }
		function buildPreviewData(item) { const confidence = estimateConfidence(item); return { id: parseInt(item.id || 0, 10), title: item.title || 'Basliksiz taslak', excerpt: 'Icerik onizlemesi ilk kayitli metinle sinirlidir. Detayli duzenleme icin taslagi acabilirsiniz.', meta: item.score_fail || 'Meta description bilgisi su an kuyruk verisinde bulunmuyor.', seoScore: parseInt(item.seo_score || 0, 10), readScore: parseInt(item.read_score || 0, 10), traffic: estimateTraffic(item), keywordVolume: estimateKeywordVolume(item), confidence: confidence.label, confidenceTone: confidence.tone, faqCount: 0, internalLinks: [], editUrl: item.edit_url || '#' }; }
		function queueRowHtmlEnhanced(item, index) { const categories = (item.categories || []).join(', ') || '--'; const seoScore = parseInt(item.seo_score || 0, 10); const readScore = parseInt(item.read_score || 0, 10); const traffic = estimateTraffic(item); const keywordVolume = estimateKeywordVolume(item); const confidence = estimateConfidence(item); const statusLabel = item.score_fail ? 'Basarisiz' : (parseInt(item.attempts || 0, 10) > 0 ? 'SEO Optimize' : 'Bekliyor'); const statusTone = item.score_fail ? 'bad' : (parseInt(item.attempts || 0, 10) > 0 ? 'warn' : 'idle'); const preview = buildPreviewData(item); const lastAction = item.date ? escapeHtml(String(item.date)) : 'Henuz yok'; return '<tr data-post-id=\"' + escapeHtml(item.id) + '\"><td><div class=\"aiseo-ap-row-title\"><a href=\"' + escapeHtml(item.edit_url) + '\">' + escapeHtml(item.title || 'Basliksiz taslak') + '</a><span class=\"aiseo-ap-row-meta\">' + escapeHtml((index + 1) + '. sirada') + '</span></div></td><td>' + escapeHtml(categories) + '</td><td><span class=\"aiseo-ap-soft-badge aiseo-ap-soft-badge--' + scoreTone(seoScore) + '\">' + (seoScore > 0 ? escapeHtml(seoScore) : '--') + '</span></td><td><span class=\"aiseo-ap-soft-badge aiseo-ap-soft-badge--' + scoreTone(readScore) + '\">' + (readScore > 0 ? escapeHtml(readScore) : '--') + '</span></td><td>' + (traffic ? '~' + escapeHtml(traffic) : '--') + '</td><td>' + (keywordVolume ? escapeHtml(keywordVolume) : '--') + '</td><td><span class=\"aiseo-ap-soft-badge aiseo-ap-soft-badge--' + confidence.tone + '\">' + escapeHtml(confidence.label) + '</span></td><td>' + lastAction + '</td><td><span class=\"aiseo-ap-status-badge aiseo-ap-status-badge--' + statusTone + '\"' + (item.score_fail ? ' title=\"' + escapeHtml(item.score_fail) + '\"' : '') + '>' + escapeHtml(statusLabel) + '</span></td><td><div class=\"aiseo-ap-row-actions\"><button type=\"button\" class=\"aiseo-ap-icon-btn aiseo-ap-preview-btn\" title=\"Onizle\" data-preview=\"' + escapeHtml(JSON.stringify(preview)) + '\"><span class=\"dashicons dashicons-visibility\"></span></button><a href=\"' + escapeHtml(item.edit_url) + '\" class=\"aiseo-ap-icon-btn\" title=\"Duzenle\"><span class=\"dashicons dashicons-edit\"></span></a><button type=\"button\" class=\"aiseo-ap-icon-btn aiseo-ap-regenerate-btn\" data-post-id=\"' + escapeHtml(item.id) + '\" title=\"Yeniden Uret\"><span class=\"dashicons dashicons-update\"></span></button><button type=\"button\" class=\"aiseo-ap-icon-btn aiseo-ap-publish-btn' + (index === 0 ? '' : ' is-disabled') + '\" data-post-id=\"' + escapeHtml(item.id) + '\" title=\"' + escapeHtml(index === 0 ? 'Hemen Yayinla' : 'Sadece ilk siradaki draft manuel calistirilabilir') + '\"' + (index === 0 ? '' : ' disabled') + '><span class=\"dashicons dashicons-megaphone\"></span></button><button type=\"button\" class=\"aiseo-ap-icon-btn aiseo-ap-skip-btn\" data-post-id=\"' + escapeHtml(item.id) + '\" title=\"Kuyruktan Cikar\"><span class=\"dashicons dashicons-dismiss\"></span></button></div></td></tr>'; }
		function buildQueueTableEnhanced(queue) { return '<div class=\"aiseo-ap-table-scroller\"><table class=\"aiseo-table aiseo-ap-table\"><thead><tr><th>Baslik</th><th>Kategori</th><th>SEO</th><th>Okunabilirlik</th><th>Tahmini Trafik</th><th>Keyword Volume</th><th>AI Confidence</th><th>Son Islem</th><th>Durum</th><th>Aksiyonlar</th></tr></thead><tbody id=\"aiseo-ap-queue-body\">' + queue.map((item, index) => queueRowHtmlEnhanced(item, index)).join('') + '</tbody></table></div>'; }
		function renderQueue(queue) { if (!queueWrap) return; if (!queue.length) { queueWrap.innerHTML = '<div class=\"aiseo-ap-empty-state\"><div class=\"aiseo-ap-empty-state__icon\"><span class=\"dashicons dashicons-saved\"></span></div><h3>Kuyruk temiz gorunuyor</h3><p>Yeni draft olusturuldugunda burada otomatik olarak pipeline kartlari gorunecek.</p><button type=\"button\" class=\"button button-primary aiseo-ap-proxy-action\" data-target=\"aiseo-ap-trigger\">AI Queue Baslat</button></div>'; initProxyActions(); return; } queueWrap.innerHTML = buildQueueTableEnhanced(queue); bindQueueActions(); }
		function updateQueueKpi(total) { const el = document.querySelector('.aiseo-ap-kpi__value--queue-count'); if (!el) return; const safeTotal = Number.isFinite(total) ? Math.max(0, total) : 0; el.textContent = String(safeTotal); el.setAttribute('data-counter-target', String(safeTotal)); }
	}

	/* ------------------------------------------------------------------ */
	/* API Module                                                           */
	/* ------------------------------------------------------------------ */
	const API = {
		async request(endpoint, method, body, opts) {
			const timeout = opts?.timeout || 0;
			const controller = timeout && window.AbortController ? new AbortController() : null;
			let timer = null;
			const options = {
				method:  method || 'GET',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
			};
			if (controller) {
				options.signal = controller.signal;
				timer = setTimeout(() => controller.abort(), timeout);
			}
			if (body) options.body = JSON.stringify(body);
			try {
				const res = await fetch(restUrl + 'aiseo/v1' + endpoint, options);
				const text = await res.text();
				let json = {};
				try {
					json = text ? JSON.parse(text) : {};
				} catch (e) {
					json = { message: text.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim() };
				}
				if (!res.ok) throw json;
				return json;
			} catch (e) {
				if (e?.name === 'AbortError') {
					throw { message: 'Istek zaman asimina ugradi. Listeyi yenile ile tekrar deneyin.' };
				}
				throw e;
			} finally {
				if (timer) clearTimeout(timer);
			}
		},
		analyzePost:      (pid, force) => API.request('/analyze/' + pid, 'POST', { force: !!force }),
		getAnalysis:      (pid)        => API.request('/analyze/' + pid),
		optimize:         (pid, op)    => API.request('/optimize', 'POST', { post_id: pid, operation: op }),
		fullOptimize:     (pid, data)  => API.request('/optimize/full', 'POST', Object.assign({ post_id: pid }, data || {})),
		agentOptimize:    (data)       => API.request('/agent/optimize', 'POST', data),
		agentApply:       (data)       => API.request('/agent/apply', 'POST', data),
		regeneratePost:   (pid)        => API.request('/regenerate/' + pid, 'POST'),
		applyOptimize:    (data)       => API.request('/optimize/apply', 'POST', data),
		bulkAnalyze:      (ids)        => API.request('/bulk-analyze', 'POST', { post_ids: ids }),
		generateArticle:  (params)     => API.request('/generate', 'POST', params),
		createDraft:      (data)       => API.request('/generate/create-draft', 'POST', data),
		getLinklessPosts: ()           => API.request('/links/missing?limit=50', 'GET', null, { timeout: 25000 }),
		getLinks:         (pid)        => API.request('/links/' + pid),
		computeLinks:     (pid)        => API.request('/links/' + pid + '/compute', 'POST'),
		applyLinks:       (pid, ids, content, autoSave) => {
			const body = { post_id: pid, suggestion_ids: ids };
			if (typeof content === 'string') body.content = content;
			if (autoSave) body.auto_save = true;
			return API.request('/links/apply', 'POST', body);
		},
		optimizeTags:     (pid, data)  => API.request('/tags/optimize/' + pid, 'POST', data || {}),
		getSettings:      ()           => API.request('/settings'),
		saveSettings:     (data)       => API.request('/settings', 'POST', data),
		testKey:          (data)       => API.request('/settings/test-key', 'POST', data || {}),
		getDashboard:          ()       => API.request('/dashboard'),
		getAutoPublisherSettings: ()   => API.request('/auto-publisher/settings'),
		saveAutoPublisherSettings: (d) => API.request('/auto-publisher/settings', 'POST', d),
		triggerAutoPublisher:  (postId) => API.request('/auto-publisher/trigger', 'POST', postId ? { post_id: postId } : null),
		getAutoPublisherQueue: ()      => API.request('/auto-publisher/queue'),
		refreshAutoPublisherQueue: ()  => API.request('/auto-publisher/queue/refresh', 'POST'),
		maintainAutoPublisher: (data)  => API.request('/auto-publisher/maintenance', 'POST', data || {}),
		skipAutoPublisherPost: (pid, skip) => API.request('/auto-publisher/skip/' + pid, 'POST', { skip: !!skip }),
	};

	/* ------------------------------------------------------------------ */
	/* UI Module                                                            */
	/* ------------------------------------------------------------------ */
	const UI = {
		notice(containerId, message, type) {
			const el = document.getElementById(containerId);
			if (!el) return;
			el.innerHTML = '<div class="aiseo-notice aiseo-notice--' + (type || 'info') + '">' +
				escapeHtml(message) + '</div>';
			el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
			setTimeout(() => { if (el) el.innerHTML = ''; }, 6000);
		},
		spin(elOrId, on) {
			const el = typeof elOrId === 'string' ? document.getElementById(elOrId) : elOrId;
			if (!el) return;
			if (on) {
				el.style.display = '';
			} else {
				el.style.display = 'none';
			}
		},
		loading(btnEl, on) {
			if (!btnEl) return;
			if (on) {
				btnEl.disabled = true;
				btnEl._origText = btnEl.innerHTML;
				btnEl.innerHTML = '<span class="aiseo-spinner"></span>';
			} else {
				btnEl.disabled = false;
				if (btnEl._origText) btnEl.innerHTML = btnEl._origText;
			}
		},
		showModal(opts) {
			const overlay  = document.getElementById('aiseo-modal-overlay');
			const titleEl  = document.getElementById('aiseo-modal-title');
			const beforeEl = document.getElementById('aiseo-modal-before');
			const afterEl  = document.getElementById('aiseo-modal-after');
			const applyBtn = document.getElementById('aiseo-modal-apply');
			if (!overlay) return;

			if (titleEl)  titleEl.textContent  = opts.title  || i18n.after || 'AI Önerisi';
			if (beforeEl) beforeEl.textContent = opts.before || '';
			if (afterEl)  afterEl.textContent  = opts.after  || '';
			overlay.style.display = 'flex';

			if (applyBtn && opts.onApply) {
				const handler = () => {
					opts.onApply();
					applyBtn.removeEventListener('click', handler);
					UI.closeModal();
				};
				applyBtn.onclick = handler;
			}
		},
		closeModal() {
			const overlay = document.getElementById('aiseo-modal-overlay');
			if (overlay) overlay.style.display = 'none';
		},
		updateScoreBadge(cellId, seoScore, readScore) {
			const seoCell  = document.getElementById('seo-score-'  + cellId);
			const readCell = document.getElementById('read-score-' + cellId);
			if (seoCell)  seoCell.innerHTML  = scoreBadge(seoScore);
			if (readCell) readCell.innerHTML = scoreBadge(readScore);
		},
	};

	function escapeHtml(s) {
		return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
	}
	function scoreColor(s) {
		return s >= 80 ? 'green' : s >= 60 ? 'orange' : 'red';
	}
	function scoreBadge(s) {
		const c = s > 0 ? scoreColor(s) : 'none';
		const l = s >= 80 ? 'İyi' : s >= 60 ? 'Geliştirilebilir' : s > 0 ? 'Zayıf' : '—';
		const v = s > 0 ? s + ' – ' + l : '—';
		return '<span class="aiseo-badge aiseo-badge--' + c + '">' + escapeHtml(v) + '</span>';
	}

	function uniqueIds(values) {
		return Array.from(new Set((values || [])
			.map((value) => parseInt(value))
			.filter((value) => Number.isFinite(value) && value > 0)));
	}

	function setProgress(progressBar, statusEl, processed, total, prefix) {
		const safeTotal = total > 0 ? total : 1;
		const pct = Math.round((processed / safeTotal) * 100);
		if (progressBar) progressBar.style.width = pct + '%';
		if (statusEl) statusEl.textContent = (prefix ? prefix + ' ' : '') + processed + ' / ' + total;
	}

	async function runBulkAnalyzeQueue(postIds, opts) {
		const ids = uniqueIds(postIds);
		const options = opts || {};
		const batchSize = options.batchSize || 5;
		let processed = 0;
		let succeeded = 0;
		let failed = 0;

		if (!ids.length) {
			return { processed, succeeded, failed };
		}

		if (options.button) UI.loading(options.button, true);
		if (options.progressWrap) UI.spin(options.progressWrap, true);
		setProgress(options.progressBar, options.statusEl, 0, ids.length, options.statusPrefix);

		try {
			for (let i = 0; i < ids.length; i += batchSize) {
				const batch = ids.slice(i, i + batchSize);
				try {
					const res = await API.bulkAnalyze(batch);
					const results = res.data?.results || [];
					const resultMap = new Map(results.map((item) => [parseInt(item.post_id), item]));

					batch.forEach((postId) => {
						const result = resultMap.get(postId);
						if (result?.success) succeeded++;
						else failed++;
						if (options.onResult) options.onResult(postId, result || { post_id: postId, success: false });
						processed++;
						setProgress(options.progressBar, options.statusEl, processed, ids.length, options.statusPrefix);
					});
				} catch (e) {
					batch.forEach((postId) => {
						failed++;
						if (options.onResult) options.onResult(postId, { post_id: postId, success: false, error: e?.message || i18n.error });
						processed++;
						setProgress(options.progressBar, options.statusEl, processed, ids.length, options.statusPrefix);
					});
				}
			}
		} finally {
			if (options.button) UI.loading(options.button, false);
		}

		return { processed, succeeded, failed };
	}

	/* ------------------------------------------------------------------ */
	/* Modal Close Button                                                   */
	/* ------------------------------------------------------------------ */
	function initModalClose() {
		const closeBtn  = document.getElementById('aiseo-modal-close');
		const cancelBtn = document.getElementById('aiseo-modal-cancel');
		const overlay   = document.getElementById('aiseo-modal-overlay');
		if (closeBtn)  closeBtn.addEventListener('click',  UI.closeModal);
		if (cancelBtn) cancelBtn.addEventListener('click', UI.closeModal);
		if (overlay) {
			overlay.addEventListener('click', (e) => {
				if (e.target === overlay) UI.closeModal();
			});
		}
	}

	/* ------------------------------------------------------------------ */
	/* Post List — Inline Analyze Button                                   */
	/* ------------------------------------------------------------------ */
	function initPostListAnalyze() {
		document.querySelectorAll('.aiseo-btn-analyze').forEach((btn) => {
			btn.addEventListener('click', async () => {
				const postId = btn.dataset.postId;
				if (!postId) return;
				UI.loading(btn, true);
				try {
					const res = await API.analyzePost(postId, true);
					const data = res.data || {};
					const row  = btn.closest('tr');
					if (row) {
						const seoCell  = row.querySelector('.aiseo-seo-score-cell');
						const readCell = row.querySelector('.aiseo-read-score-cell');
						if (seoCell) seoCell.innerHTML = scoreBadge(data.seo_score || 0);
						if (readCell) readCell.innerHTML = scoreBadge(data.readability_score || 0);
					}
					UI.notice('aiseo-posts-notice', 'Analiz tamamlandı. SEO: ' + (data.seo_score || 0), 'success');
				} catch (e) {
					UI.notice('aiseo-posts-notice', (e.message || i18n.error), 'error');
				} finally {
					UI.loading(btn, false);
				}
			});
		});
	}

	/* ------------------------------------------------------------------ */
	/* Post Detail — Optimize Buttons                                      */
	/* ------------------------------------------------------------------ */
	function getCriterionFixConfig(criterionId) {
		const operationMap = {
			keyword_in_title: 'optimize_title',
			keyword_in_meta_description: 'optimize_meta',
			meta_description_length: 'optimize_meta',
			keyword_in_first_paragraph: 'improve_intro',
			keyword_density: 'improve_keyword_density',
			headings_structure: 'improve_structure',
			keyword_in_headings: 'improve_structure',
			image_alt_text: 'optimize_image_alts',
			sentence_length: 'improve_readability',
			paragraph_length: 'improve_readability',
			passive_voice: 'improve_readability',
			transition_words: 'improve_readability',
			consecutive_sentences: 'improve_readability',
			subheading_distribution: 'improve_structure',
			flesch_reading_ease: 'improve_readability',
			text_complexity: 'improve_readability',
		};

		if (operationMap[criterionId]) {
			return { type: 'operation', operation: operationMap[criterionId] };
		}
		if (['internal_links', 'content_length', 'focus_keyword_present'].includes(criterionId)) {
			return { type: 'full' };
		}
		return {
			type: 'unsupported',
			reason: 'Bu uyari icerikten cok eklenti ayari veya manuel baglanti gerektiriyor.',
		};
	}

	function getEditorMetaValue(postId) {
		const selectors = [
			'#yoast_wpseo_metadesc',
			'#_yoast_wpseo_metadesc',
			'textarea[name="yoast_wpseo_metadesc"]',
			'textarea[name="_yoast_wpseo_metadesc"]',
			'#rank_math_description',
			'textarea[name="rank_math_description"]',
			'#aioseo-post-settings-description',
			'textarea[name="aioseo_description"]'
		];

		for (const selector of selectors) {
			const field = document.querySelector(selector);
			if (field && 'value' in field && String(field.value || '').trim()) {
				return String(field.value || '').trim();
			}
		}

		return localStorage.getItem('aiseo_pending_meta_' + (postId || Config.postId)) || '';
	}

	function getFullOptimizePayload(postId) {
		return {
			title: document.getElementById('title')?.value || '',
			content: getEditorContent(),
			meta: getEditorMetaValue(postId),
			current_tags: getCurrentEditorTags(),
			include_internal_links: true,
			optimize_tags: true,
		};
	}

	function getDetailOperationLabel(operation) {
		return {
			optimize_title: 'Baslik Iyilestirme',
			optimize_meta: 'Meta Aciklama',
			improve_intro: 'Giris Paragraflari',
			improve_structure: 'Baslik Yapisi',
			improve_readability: 'Okunabilirlik',
			improve_keyword_density: 'Keyword Yogunlugu',
			add_faq: 'FAQ Bolumu',
			improve_conclusion: 'Sonuc Bolumu',
			optimize_image_alts: 'Gorsel Alt Metinleri',
			add_internal_links: 'Ic Linkler',
			optimize_tags: 'Etiketler',
			full_content_optimization: 'Tam Icerik Revizyonu',
		}[operation] || 'AI Onerisi';
	}

	function initPostDetailOptimize() {
		const detailWrap = document.getElementById('aiseo-post-detail');
		if (!detailWrap) return;
		if (detailWrap.dataset.aiseoInit === '1') return;
		detailWrap.dataset.aiseoInit = '1';
		const postId = detailWrap.dataset.postId;

		document.querySelectorAll('.aiseo-btn-optimize').forEach((btn) => {
			btn.addEventListener('click', async () => {
				const operation = btn.dataset.operation;
				const loadingEl = document.getElementById('aiseo-optimize-loading');
				UI.loading(btn, true);
				UI.spin(loadingEl, true);
				try {
					const res  = await API.optimize(postId, operation);
					const data = res.data || {};
					const opLabels = {
						optimize_title:          'Başlık İyileştirme',
						optimize_meta:           'Meta Açıklama',
						improve_intro:           'Giriş Paragrafı',
						improve_readability:     'Okunabilirlik',
						improve_keyword_density: 'Keyword Yoğunluğu',
						add_faq:                 'FAQ Bölümü',
						improve_conclusion:      'Sonuç Bölümü',
					};
					UI.showModal({
						title:  opLabels[operation] || 'AI Önerisi',
						before: data.before || '',
						after:  data.after  || '',
						onApply: async () => {
							try {
								await API.applyOptimize({
									post_id:   parseInt(postId),
									operation: operation,
									field:     data.field     || 'post_content',
									meta_key:  data.meta_key  || '',
									new_value: data.after     || '',
								});
								UI.notice('aiseo-posts-notice', i18n.success + ' ' + (i18n.revisionNote || ''), 'success');
							} catch (e) {
								UI.notice('aiseo-posts-notice', e.message || i18n.error, 'error');
							}
						},
					});
				} catch (e) {
					UI.notice('aiseo-posts-notice', e.message || i18n.error, 'error');
				} finally {
					UI.loading(btn, false);
					UI.spin(loadingEl, false);
				}
			});
		});

		detailWrap.addEventListener('click', async (event) => {
			const button = event.target.closest('.aiseo-criterion__fix');
			if (!button) return;

			event.preventDefault();
			const fixAction = button.dataset.fixAction || '';
			const operation = button.dataset.operation || '';
			const reason = button.dataset.reason || '';
			const loadingEl = document.getElementById('aiseo-optimize-loading');

			if (fixAction === 'unsupported') {
				UI.notice('aiseo-posts-notice', reason || 'Bu uyari manuel ayar gerektiriyor.', 'warning');
				return;
			}

			UI.loading(button, true);
			UI.spin(loadingEl, true);
			try {
				if (fixAction === 'operation' && operation) {
					const res = await API.optimize(postId, operation);
					const data = res.data || {};
					UI.showModal({
						title:  getDetailOperationLabel(operation),
						before: data.before || '',
						after:  data.after  || '',
						onApply: async () => {
							try {
								await API.applyOptimize({
									post_id:   parseInt(postId),
									operation: operation,
									field:     data.field     || 'post_content',
									meta_key:  data.meta_key  || '',
									new_value: data.after     || '',
								});
								UI.notice('aiseo-posts-notice', 'Degisiklik uygulandi. Yaziyi yeniden analiz etmek iyi olur.', 'success');
							} catch (e) {
								UI.notice('aiseo-posts-notice', e.message || i18n.error, 'error');
							}
						},
					});
					return;
				}

				if (fixAction === 'full') {
					const res = await API.fullOptimize(postId, {
						include_internal_links: true,
						optimize_tags: true,
					});
					const data = res.data || {};
					UI.showModal({
						title: 'Tam Duzeltme',
						before: data.steps?.find((step) => step.operation === 'full_content_optimization')?.before || '',
						after:  data.content || '',
						onApply: async () => {
							try {
								await API.agentApply({
									post_id: postId,
									title: data.title || '',
									content: data.content || '',
									meta: data.meta || '',
									tags: data.tags || [],
								});
								UI.notice('aiseo-posts-notice', 'Tam duzeltme uygulandi. Yaziyi yeniden analiz etmek iyi olur.', 'success');
							} catch (e) {
								UI.notice('aiseo-posts-notice', e.message || i18n.error, 'error');
							}
						},
					});
				}
			} catch (e) {
				UI.notice('aiseo-posts-notice', e.message || i18n.error, 'error');
			} finally {
				UI.loading(button, false);
				UI.spin(loadingEl, false);
			}
		});
	}

	/* ------------------------------------------------------------------ */
	/* Bulk Analysis                                                        */
	/* ------------------------------------------------------------------ */
	function initBulkAnalysis() {
		const startBtn   = document.getElementById('aiseo-bulk-start');
		const selectAll  = document.getElementById('aiseo-select-all');
		const selectAllH = document.getElementById('aiseo-select-all-header');
		const filter     = document.getElementById('aiseo-bulk-filter');
		const search     = document.getElementById('aiseo-bulk-search');

		if (selectAll) {
			selectAll.addEventListener('change', () => {
				document.querySelectorAll('.aiseo-post-select').forEach((cb) => {
					cb.checked = selectAll.checked;
				});
			});
		}
		if (selectAllH) {
			selectAllH.addEventListener('change', () => {
				document.querySelectorAll('.aiseo-post-select').forEach((cb) => {
					cb.checked = selectAllH.checked;
				});
			});
		}

		if (filter) {
			const params = new URLSearchParams(window.location.search);
			const initialFilter = params.get('score_filter');
			if (initialFilter) {
				filter.value = initialFilter;
			}
			filter.addEventListener('change', () => {
				const val = filter.value;
				document.querySelectorAll('#aiseo-bulk-table tbody tr').forEach((row) => {
					if (!val) { row.style.display = ''; return; }
					const color = row.dataset.scoreColor || 'none';
					row.style.display = (color === val) ? '' : 'none';
				});
			});
			if (filter.value) {
				filter.dispatchEvent(new Event('change'));
			}
		}

		if (search) {
			search.addEventListener('input', () => {
				const q = search.value.toLowerCase();
				document.querySelectorAll('#aiseo-bulk-table tbody tr').forEach((row) => {
					const title = row.dataset.title || '';
					row.style.display = title.includes(q) ? '' : 'none';
				});
			});
		}

		if (!startBtn) return;

		startBtn.addEventListener('click', async () => {
			const selected = Array.from(document.querySelectorAll('.aiseo-post-select:checked'))
				.map((cb) => parseInt(cb.value));

			if (selected.length === 0) {
				UI.notice('aiseo-bulk-notice', i18n.selectPosts || 'En az bir yazı seçin.', 'warning');
				return;
			}

			const progressWrap = document.getElementById('aiseo-bulk-progress-wrap');
			const progressBar  = document.getElementById('aiseo-bulk-progress');
			const statusEl     = document.getElementById('aiseo-bulk-status');

			UI.spin(progressWrap, true);
			UI.loading(startBtn, true);

			const total     = selected.length;
			let processed   = 0;
			let succeeded   = 0;
			let failed      = 0;
			const batchSize = 5;

			for (let i = 0; i < selected.length; i += batchSize) {
				const batch = selected.slice(i, i + batchSize);
				try {
					const res = await API.bulkAnalyze(batch);
					(res.data?.results || []).forEach((r) => {
						if (r.success) {
							UI.updateScoreBadge(r.post_id, r.seo_score, r.readability_score);
							updateBulkRow(r);
							succeeded++;
						} else {
							failed++;
						}
						processed++;
					});
				} catch (e) {
					processed += batch.length;
					failed += batch.length;
				}
				const pct = Math.round((processed / total) * 100);
				if (progressBar) progressBar.style.width = pct + '%';
				if (statusEl) statusEl.textContent = processed + ' / ' + total;
			}

			UI.loading(startBtn, false);
			UI.notice('aiseo-bulk-notice', 'Toplu analiz tamamlandı. Başarılı: ' + succeeded + ', hata: ' + failed + '.', failed ? 'warning' : 'success');
		});
	}

	/* ------------------------------------------------------------------ */
	/* Article Generator                                                    */
	/* ------------------------------------------------------------------ */
	function updateBulkRow(result) {
		const row = document.querySelector('#aiseo-bulk-table tr[data-post-id="' + result.post_id + '"]');
		if (!row) return;
		row.dataset.scoreColor = scoreColor(result.seo_score || 0);

		const lastCell = document.getElementById('analysis-date-' + result.post_id);
		if (lastCell) lastCell.textContent = 'şimdi';
	}

	let lastGenerationResult = null;

	function initArticleGenerator() {
		const genBtn      = document.getElementById('aiseo-generate-btn');
		const draftBtn    = document.getElementById('aiseo-create-draft-btn');
		const loadingEl   = document.getElementById('aiseo-generate-loading');
		const previewCard = document.getElementById('aiseo-preview-card');
		const wordCountEl = document.getElementById('aiseo-gen-word-count');
		const tokenHintEl = document.querySelector('[data-generator-token-estimate]');

		if (wordCountEl && tokenHintEl) {
			const syncTokenHint = () => {
				tokenHintEl.textContent = 'Tahmini taslak seviyesi: ' + (wordCountEl.value || '1200') + ' kelime';
			};
			wordCountEl.addEventListener('change', syncTokenHint);
			syncTokenHint();
		}

		if (genBtn) {
			genBtn.addEventListener('click', async () => {
				const keyword = document.getElementById('aiseo-gen-keyword')?.value?.trim();
				if (!keyword) {
					UI.notice('aiseo-generator-notice', 'Anahtar kelime zorunludur.', 'warning');
					return;
				}

				UI.loading(genBtn, true);
				UI.spin(loadingEl, true);

				const params = {
					keyword:      keyword,
					title:        document.getElementById('aiseo-gen-title')?.value?.trim()        || '',
					tone:         document.getElementById('aiseo-gen-tone')?.value                 || 'professional',
					language:     document.getElementById('aiseo-gen-language')?.value             || 'tr',
					target_words: parseInt(document.getElementById('aiseo-gen-word-count')?.value) || 1200,
					include_faq:  document.getElementById('aiseo-gen-include-faq')?.checked        ?? true,
					aux_keywords: document.getElementById('aiseo-gen-aux-keywords')?.value?.trim() || '',
					category:     parseInt(document.getElementById('aiseo-gen-category')?.value)   || 0,
					auto_internal_links: document.getElementById('aiseo-gen-auto-links')?.checked  || false,
				};

				try {
					const res = await API.generateArticle(params);
					lastGenerationResult = res.data || {};
					lastGenerationResult.category = params.category;
					lastGenerationResult.auto_internal_links = params.auto_internal_links;
					renderArticlePreview(lastGenerationResult);
					if (previewCard) previewCard.style.display = '';
				} catch (e) {
					UI.notice('aiseo-generator-notice', e.message || i18n.error, 'error');
				} finally {
					UI.loading(genBtn, false);
					UI.spin(loadingEl, false);
				}
			});
		}

		if (draftBtn) {
			draftBtn.addEventListener('click', async () => {
				if (!lastGenerationResult) return;
				UI.loading(draftBtn, true);
				try {
					const res = await API.createDraft(lastGenerationResult);
					const editUrl = res.data?.edit_url;
					const linksAdded = parseInt(res.data?.links_added || 0);
					if (editUrl) {
						if (confirm((i18n.draftCreated || 'Taslak oluşturuldu! Düzenlemek ister misiniz?'))) {
							window.location.href = editUrl;
						}
					} else {
						UI.notice('aiseo-generator-notice', 'Taslak oluşturuldu.', 'success');
					}
				} catch (e) {
					UI.notice('aiseo-generator-notice', e.message || i18n.error, 'error');
				} finally {
					UI.loading(draftBtn, false);
				}
			});
		}
	}

	function renderArticlePreview(data) {
		const setEl = (id, val) => {
			const el = document.getElementById(id);
			if (el) el.textContent = val || '';
		};
		setEl('aiseo-preview-title',   data.title            || '');
		setEl('aiseo-preview-meta',    data.meta_description || '');
		setEl('aiseo-preview-wc',      (data.word_count || 0) + ' kelime');
		setEl('aiseo-preview-keyword', data.focus_keyword    || '');

		const contentEl = document.getElementById('aiseo-preview-content');
		if (contentEl) {
			contentEl.innerHTML = sanitizePreviewHtml(data.content || '');
		}
	}

	function sanitizePreviewHtml(html) {
		const allowed = ['p','h2','h3','h4','ul','ol','li','strong','em','br'];
		const div = document.createElement('div');
		div.innerHTML = html;
		div.querySelectorAll('*').forEach((el) => {
			if (!allowed.includes(el.tagName.toLowerCase())) {
				el.replaceWith(document.createTextNode(el.textContent));
			}
			Array.from(el.attributes).forEach((attr) => el.removeAttribute(attr.name));
		});
		return div.innerHTML;
	}

	/* ------------------------------------------------------------------ */
	/* Internal Links                                                       */
	/* ------------------------------------------------------------------ */
	function initInternalLinks() {
		const computeBtn = document.getElementById('aiseo-compute-links');
		const applyBtn   = document.getElementById('aiseo-apply-links');
		const resultsEl  = document.getElementById('aiseo-links-results');
		const loadingEl  = document.getElementById('aiseo-links-loading');
		const selectAll  = document.getElementById('aiseo-select-all-links');
		const tbody      = document.getElementById('aiseo-links-tbody');
		const postSelect = document.getElementById('aiseo-link-post-select');
		const missingBody = document.getElementById('aiseo-linkless-tbody');
		const refreshBtn  = document.getElementById('aiseo-refresh-linkless');

		if (missingBody) loadLinklessPosts(missingBody);
		if (refreshBtn) {
			refreshBtn.addEventListener('click', () => loadLinklessPosts(missingBody, refreshBtn));
		}

		if (selectAll) {
			selectAll.addEventListener('change', () => {
				document.querySelectorAll('.aiseo-link-select').forEach((cb) => {
					cb.checked = selectAll.checked;
				});
			});
		}

		if (computeBtn) {
			computeBtn.addEventListener('click', async () => {
				const postId = postSelect?.value;
				if (!postId) {
					UI.notice('aiseo-links-notice', 'Lütfen bir yazı seçin.', 'warning');
					return;
				}

				UI.loading(computeBtn, true);
				UI.spin(loadingEl, true);
				if (resultsEl) resultsEl.style.display = 'none';

				try {
					const res = await API.computeLinks(postId);
					const suggestions = res.data?.suggestions || [];
					renderLinkSuggestions(tbody, suggestions);
					if (resultsEl) resultsEl.style.display = '';
					if (!suggestions.length) {
						UI.notice('aiseo-links-notice', 'Bu yazı için link önerisi bulunamadı.', 'info');
					}
				} catch (e) {
					UI.notice('aiseo-links-notice', e.message || i18n.error, 'error');
				} finally {
					UI.loading(computeBtn, false);
					UI.spin(loadingEl, false);
				}
			});
		}

		if (applyBtn) {
			applyBtn.addEventListener('click', async () => {
				const postId = postSelect?.value;
				const selected = Array.from(document.querySelectorAll('.aiseo-link-select:checked'))
					.map((cb) => parseInt(cb.value))
					.filter((id) => Number.isFinite(id) && id > 0);

				if (!postId || !selected.length) {
					UI.notice('aiseo-links-notice', 'Önce yazı seçin ve link önerilerini işaretleyin.', 'warning');
					return;
				}

				if (!confirm('Seçili iç linkleri yazının editöründe hazırlayayım mı? Son kaydı siz yapacaksınız.')) return;

				UI.loading(applyBtn, true);
				try {
					const res = await API.applyLinks(postId, selected);
					const data = res.data || {};
					if (!data.changed) {
						UI.notice('aiseo-links-notice', 'Seçili anchor metni yazı içinde bulunamadı; editöre aktarılacak bir değişiklik yok.', 'warning');
						return;
					}
					localStorage.setItem('aiseo_pending_link_content_' + postId, data.content || '');
					window.location.href = data.edit_url;
				} catch (e) {
					UI.notice('aiseo-links-notice', e.message || i18n.error, 'error');
				} finally {
					UI.loading(applyBtn, false);
				}
			});
		}

		document.addEventListener('click', async (event) => {
			const btn = event.target instanceof Element ? event.target.closest('.aiseo-linkless-apply') : null;
			if (!btn) return;
			event.preventDefault();

			const postId = parseInt(btn.dataset.postId);
			if (!Number.isFinite(postId) || postId <= 0) return;

			UI.loading(btn, true);
			try {
				const computeRes = await API.computeLinks(postId);
				const suggestions = computeRes.data?.suggestions || [];
				const selectedIds = suggestions
					.slice(0, 3)
					.map((item) => parseInt(item.id))
					.filter((id) => Number.isFinite(id) && id > 0);

				if (!selectedIds.length) {
					UI.notice('aiseo-links-notice', 'Bu yazi icin ayni kategoride uygulanabilir ic link bulunamadi.', 'warning');
					return;
				}

				const applyRes = await API.applyLinks(postId, selectedIds, null, true);
				const data = applyRes.data || {};
				if (!data.auto_saved) {
					UI.notice('aiseo-links-notice', 'Ic link hazirlandi ama yazida degisiklik olusmadi.', 'warning');
					return;
				}

				btn.closest('tr')?.remove();
				if (missingBody && !missingBody.querySelector('tr')) {
					missingBody.innerHTML = '<tr><td colspan="6" class="aiseo-empty">Ic linksiz yazi kalmadi.</td></tr>';
				}
				UI.notice('aiseo-links-notice', 'Ic linkler yazıya eklendi ve yazı otomatik kaydedildi.', 'success');
			} catch (e) {
				UI.notice('aiseo-links-notice', e.message || i18n.error, 'error');
			} finally {
				UI.loading(btn, false);
			}
		});
	}

	async function loadLinklessPosts(tbody, btn) {
		if (!tbody) return;
		if (btn) UI.loading(btn, true);
		tbody.innerHTML = '<tr><td colspan="6" class="aiseo-empty">Yazilar taraniyor...</td></tr>';
		try {
			const res = await API.getLinklessPosts();
			renderLinklessPosts(tbody, res.data?.posts || []);
		} catch (e) {
			tbody.innerHTML = '<tr><td colspan="6" class="aiseo-empty">' + escapeHtml(e.message || i18n.error) + '<br><button type="button" class="button" id="aiseo-linkless-retry">Tekrar Dene</button></td></tr>';
			document.getElementById('aiseo-linkless-retry')?.addEventListener('click', () => loadLinklessPosts(tbody, btn));
		} finally {
			if (btn) UI.loading(btn, false);
		}
	}

	function renderLinklessPosts(tbody, posts) {
		tbody.innerHTML = '';
		if (!posts.length) {
			tbody.innerHTML = '<tr><td colspan="6" class="aiseo-empty">Ic linksiz yazi bulunmadi.</td></tr>';
			return;
		}
		posts.forEach((post) => {
			const row = document.createElement('tr');
			row.dataset.postId = post.post_id || '';
			row.innerHTML =
				'<td><a href="' + escapeHtml(post.edit_url || '') + '">' + escapeHtml(post.title || '') + '</a></td>' +
				'<td>' + escapeHtml(post.categories || '-') + '</td>' +
				'<td>' + escapeHtml(post.word_count || 0) + '</td>' +
				'<td>' + escapeHtml(post.candidates || 0) + '</td>' +
				'<td>' + escapeHtml(post.last_update || '-') + '</td>' +
				'<td><button type="button" class="button button-primary button-small aiseo-linkless-apply" data-post-id="' + escapeHtml(post.post_id || '') + '">Ic Link Ekle</button></td>';
			tbody.appendChild(row);
		});
	}

	function renderLinkSuggestions(tbody, suggestions) {
		if (!tbody) return;
		tbody.innerHTML = '';
		if (!suggestions.length) {
			tbody.innerHTML = '<tr><td colspan="5" class="aiseo-empty">Öneri bulunamadı.</td></tr>';
			return;
		}
		suggestions.forEach((s) => {
			const row = document.createElement('tr');
			const pct = Math.round((s.similarity_score || 0) * 100);
			row.innerHTML =
				'<td><input type="checkbox" class="aiseo-link-select" value="' + (s.id || '') + '"></td>' +
				'<td><a href="' + escapeHtml(s.target_url || '') + '" target="_blank">' + escapeHtml(s.target_title || '') + '</a></td>' +
				'<td>' + escapeHtml(s.anchor_text || '') + '</td>' +
				'<td style="font-size:12px;color:#646970">' + escapeHtml(s.context_snippet || '') + '</td>' +
				'<td>' + scoreBadge(pct) + '</td>';
			tbody.appendChild(row);
		});
	}

	function initPendingLinkContent() {
		const params = new URLSearchParams(window.location.search);
		const postId = params.get('post');
		if (!postId) return;

		const key = 'aiseo_pending_link_content_' + postId;
		const content = localStorage.getItem(key);
		if (!content) return;

		const applyContent = () => {
			if (window.wp?.data?.dispatch) {
				window.wp.data.dispatch('core/editor').editPost({ content });
				localStorage.removeItem(key);
				window.alert('İç linkler editöre eklendi. Kontrol edip Güncelle butonuyla kaydedebilirsiniz.');
				return;
			}

			const textarea = document.getElementById('content');
			if (textarea) {
				textarea.value = content;
				textarea.dispatchEvent(new Event('input', { bubbles: true }));
				localStorage.removeItem(key);
				window.alert('İç linkler editöre eklendi. Kontrol edip kaydedebilirsiniz.');
			}
		};

		setTimeout(applyContent, 800);
	}

	function initEditorPanel() {
		return initEditorPanelDelegated();
	}

	let editorSuggestionState = null;
	let editorFullSuggestionState = null;
	let editorPanelEventsBound = false;

	function initEditorPanelDelegated() {
		if (editorPanelEventsBound) return;
		editorPanelEventsBound = true;

		document.addEventListener('click', async (event) => {
			const target = event.target;
			const button = target instanceof Element ? target.closest('button') : null;
			if (!button) return;

			const panel = button.closest('.aiseo-editor-panel');
			if (!panel) return;

			const postId = panel.dataset.postId || Config.postId;
			const preview = document.getElementById('aiseo-editor-preview');

			if (button.id === 'aiseo-editor-analyze') {
				event.preventDefault();
				UI.loading(button, true);
				try {
					const res = await API.analyzePost(postId, true);
					const data = res.data || {};
					const seoScore = document.getElementById('aiseo-editor-seo-score');
					const readScore = document.getElementById('aiseo-editor-read-score');
					const lastEl = document.getElementById('aiseo-editor-last');
					if (seoScore) seoScore.textContent = data.seo_score || '—';
					if (readScore) readScore.textContent = data.readability_score || '—';
					if (lastEl) lastEl.textContent = 'Son analiz: Az önce';
					renderAnalysisSummary(preview, data);
					UI.notice('aiseo-editor-notice', 'Analiz tamamlandı. SEO: ' + (data.seo_score || '—') + ', Okunabilirlik: ' + (data.readability_score || '—'), 'success');
				} catch (e) {
					UI.notice('aiseo-editor-notice', e.message || i18n.error, 'error');
				} finally {
					UI.loading(button, false);
				}
				return;
			}

			if (button.classList.contains('aiseo-criterion__fix')) {
				event.preventDefault();
				const criterionId = button.dataset.criterionId || '';
				const config = getCriterionFixConfig(criterionId);

				if (config.type === 'unsupported') {
					UI.notice('aiseo-editor-notice', config.reason || 'Bu uyari manuel ayar gerektiriyor.', 'warning');
					return;
				}

				UI.loading(button, true);
				try {
					if (config.type === 'operation' && config.operation) {
						const res = await API.optimize(postId, config.operation);
						renderEditorSuggestion(preview, res.data || {});
					} else if (config.type === 'full') {
						const res = await API.fullOptimize(postId, getFullOptimizePayload(postId));
						renderEditorFullSuggestion(preview, res.data || {});
					}
				} catch (e) {
					UI.notice('aiseo-editor-notice', e.message || i18n.error, 'error');
				} finally {
					UI.loading(button, false);
				}
				return;
			}

			if (button.classList.contains('aiseo-editor-optimize')) {
				event.preventDefault();
				UI.loading(button, true);
				try {
					const res = await API.optimize(postId, button.dataset.operation);
					renderEditorSuggestion(preview, res.data || {});
				} catch (e) {
					UI.notice('aiseo-editor-notice', e.message || i18n.error, 'error');
				} finally {
					UI.loading(button, false);
				}
				return;
			}

			if (button.id === 'aiseo-editor-fix-all') {
				event.preventDefault();
				if (!confirm('Başlık, meta, SEO ve okunabilirlik dengeli şekilde iyileştirilsin mi? Mevcut FAQ/etiketler tekrar eklenmez; değişiklikler editöre aktarılacak, kaydı siz yapacaksınız.')) return;
				UI.loading(button, true);
				try {
					const res = await API.fullOptimize(postId, getFullOptimizePayload(postId));
					renderEditorFullSuggestion(preview, res.data || {});
				} catch (e) {
					UI.notice('aiseo-editor-notice', e.message || i18n.error, 'error');
				} finally {
					UI.loading(button, false);
				}
				return;
			}

			if (button.id === 'aiseo-editor-internal-links' || button.dataset.aiseoAction === 'internal-links') {
				event.preventDefault();
				if (!confirm('Yazı içine uygun iç linkler eklensin mi? Sonuç editöre aktarılacak, kaydı siz yapacaksınız.')) return;
				UI.loading(button, true);
				UI.notice('aiseo-editor-notice', 'İç link önerileri hesaplanıyor...', 'info');
				try {
					const computeRes = await API.computeLinks(postId);
					const suggestions = computeRes.data?.suggestions || [];
					const selectedIds = suggestions
						.slice(0, 3)
						.map((item) => parseInt(item.id))
						.filter((id) => Number.isFinite(id) && id > 0);

					if (!selectedIds.length) {
						UI.notice('aiseo-editor-notice', 'Bu yazı için iç link önerisi bulunamadı.', 'info');
						return;
					}

					const applyRes = await API.applyLinks(postId, selectedIds, getEditorContent());
					const data = applyRes.data || {};
					if (!data.changed || !data.content) {
						UI.notice('aiseo-editor-notice', 'Uygulanacak iç link değişikliği bulunamadı.', 'warning');
						return;
					}

					applyEditorContent(data.content);
					renderEditorInternalLinks(preview, suggestions.filter((item) => selectedIds.includes(parseInt(item.id))));
					UI.notice('aiseo-editor-notice', 'İç linkler editöre aktarıldı. Kontrol edip Güncelle ile kaydedin.', 'success');
				} catch (e) {
					UI.notice('aiseo-editor-notice', e.message || i18n.error, 'error');
				} finally {
					UI.loading(button, false);
				}
				return;
			}

			if (button.id === 'aiseo-editor-fix-tags' || button.dataset.aiseoAction === 'fix-tags') {
				event.preventDefault();
				if (!confirm('Etiketler temiz bir SEO listesiyle değiştirilsin mi? Mevcut gereksiz/tekrar eden etiketler kaldırılabilir.')) return;
				UI.loading(button, true);
				UI.notice('aiseo-editor-notice', 'Etiketler analiz ediliyor...', 'info');
				try {
					const res = await API.optimizeTags(postId, {
						content: getEditorContent(),
						current_tags: getCurrentEditorTags(),
					});
					const tags = res.data?.tags || [];
					if (!tags.length) {
						UI.notice('aiseo-editor-notice', 'Etiket önerisi üretilemedi.', 'warning');
						return;
					}
					replaceEditorTags(tags);
					renderEditorTagsResult(preview, tags);
					UI.notice('aiseo-editor-notice', 'Etiketler güncellendi.', 'success');
				} catch (e) {
					UI.notice('aiseo-editor-notice', e.message || i18n.error, 'error');
				} finally {
					UI.loading(button, false);
				}
				return;
			}

			if (button.id === 'aiseo-editor-regenerate') {
				event.preventDefault();
				if (!confirm('Mevcut yazı baştan oluşturulsun mu? Öneri editöre aktarılacak, kaydı siz yapacaksınız.')) return;
				UI.loading(button, true);
				try {
					const res = await API.regeneratePost(postId);
					const data = res.data || {};
					data.steps = [
						{
							operation: 'regenerate_article',
							success: true,
							before: getEditorContent(),
							after: data.content || '',
						},
					];
					renderEditorFullSuggestion(preview, data);
				} catch (e) {
					UI.notice('aiseo-editor-notice', e.message || i18n.error, 'error');
				} finally {
					UI.loading(button, false);
				}
				return;
			}

			if (button.dataset.aiseoAction === 'apply-suggestion') {
				event.preventDefault();
				if (!editorSuggestionState) return;
				applyEditorSuggestion(editorSuggestionState);
				UI.notice('aiseo-editor-notice', 'Öneri editöre aktarıldı. Kontrol edip Güncelle ile kaydedin.', 'success');
				return;
			}

			if (button.dataset.aiseoAction === 'apply-full') {
				event.preventDefault();
				if (!editorFullSuggestionState) return;
				const data = editorFullSuggestionState;
				if (data.content && !isAcceptableGeneratedContent(data.content)) {
					UI.notice('aiseo-editor-notice', 'AI yaniti temiz HTML degil; editore aktarilmadi.', 'error');
					return;
				}
				if (data.title) applyEditorTitle(data.title);
				if (data.content && !applyEditorContent(data.content)) return;
				if (data.meta) applyEditorMeta(data.meta, data.post_id || Config.postId);
				if (data.tags) applyEditorTags(data.tags);
				UI.notice('aiseo-editor-notice', 'Tam düzeltme editöre aktarıldı. Kontrol edip Güncelle ile kaydedin.', 'success');
			}
		});
	}

	/* ------------------------------------------------------------------ */
	/* Agent Optimizer                                                     */
	/* ------------------------------------------------------------------ */
	const agentProposals = new Map();

	function initAgentOptimizer() {
		const startBtn = document.getElementById('aiseo-agent-start');
		const selectAll = document.getElementById('aiseo-agent-select-all');
		const selectAllHeader = document.getElementById('aiseo-agent-select-all-header');
		if (!startBtn) return;

		const toggleAll = (checked) => {
			document.querySelectorAll('.aiseo-agent-select').forEach((cb) => {
				cb.checked = checked;
			});
		};
		if (selectAll) selectAll.addEventListener('change', () => toggleAll(selectAll.checked));
		if (selectAllHeader) selectAllHeader.addEventListener('change', () => toggleAll(selectAllHeader.checked));

		startBtn.addEventListener('click', async () => {
			const selected = Array.from(document.querySelectorAll('.aiseo-agent-select:checked'))
				.map((cb) => parseInt(cb.value))
				.filter((id) => Number.isFinite(id) && id > 0);
			if (!selected.length) {
				UI.notice('aiseo-agent-notice', 'En az bir yazı seçin.', 'warning');
				return;
			}
			if (selected.length > 3) {
				UI.notice('aiseo-agent-notice', 'İlk sürümde aynı anda en fazla 3 yazı seçin. DeepSeek tam düzeltme işlemi uzun sürebilir.', 'warning');
				return;
			}

			const targetSeo = parseInt(document.getElementById('aiseo-agent-target-seo')?.value) || 80;
			const targetRead = parseInt(document.getElementById('aiseo-agent-target-read')?.value) || 75;
			const progressWrap = document.getElementById('aiseo-agent-progress-wrap');
			const progressBar = document.getElementById('aiseo-agent-progress');
			const statusEl = document.getElementById('aiseo-agent-status');

			UI.loading(startBtn, true);
			UI.spin(progressWrap, true);
			let done = 0;
			let ready = 0;
			let skipped = 0;
			let failed = 0;

			for (const postId of selected) {
				const row = document.querySelector('#aiseo-agent-table tr[data-post-id="' + postId + '"]');
				setAgentState(row, 'Analiz ediliyor...');
				try {
					const res = await API.agentOptimize({
						post_id: postId,
						target_seo: targetSeo,
						target_readability: targetRead,
					});
					const data = res.data || {};
					if (data.skipped) {
						skipped++;
						setAgentState(row, data.reason || 'Hedefte');
					} else {
						ready++;
						agentProposals.set(postId, data);
						setAgentState(row, 'Öneri hazır');
						renderAgentAction(row, postId);
					}
					if (data.before) updateAgentScores(row, data.before.seo_score, data.before.readability_score);
				} catch (e) {
					failed++;
					setAgentState(row, e.message || 'Hata');
				}
				done++;
				const pct = Math.round((done / selected.length) * 100);
				if (progressBar) progressBar.style.width = pct + '%';
				if (statusEl) statusEl.textContent = done + ' / ' + selected.length;
			}

			UI.loading(startBtn, false);
			UI.notice('aiseo-agent-notice', 'Agent tamamlandı. Hazır: ' + ready + ', hedefte: ' + skipped + ', hata: ' + failed + '.', failed ? 'warning' : 'success');
		});

		document.addEventListener('click', async (event) => {
			const btn = event.target.closest('.aiseo-agent-apply');
			if (!btn) return;
			event.preventDefault();
			const postId = parseInt(btn.dataset.postId);
			const proposal = agentProposals.get(postId);
			if (!proposal) return;
			if (!confirm('Bu DeepSeek önerisi yazıya uygulansın mı? Uygulama öncesinde revision oluşturulur.')) return;

			const row = btn.closest('tr');
			UI.loading(btn, true);
			setAgentState(row, 'Uygulanıyor...');
			try {
				const res = await API.agentApply({
					post_id: postId,
					title: proposal.title || '',
					content: proposal.content || '',
					meta: proposal.meta || '',
					tags: proposal.tags || [],
				});
				const after = res.data?.after || {};
				updateAgentScores(row, after.seo_score || 0, after.readability_score || 0);
				setAgentState(row, 'Uygulandı');
				btn.remove();
				UI.notice('aiseo-agent-notice', 'Öneri uygulandı ve yazı yeniden analiz edildi.', 'success');
			} catch (e) {
				setAgentState(row, e.message || 'Uygulama hatası');
				UI.notice('aiseo-agent-notice', e.message || i18n.error, 'error');
			} finally {
				UI.loading(btn, false);
			}
		});
	}

	function setAgentState(row, text) {
		const cell = row?.querySelector('.aiseo-agent-state');
		if (cell) cell.textContent = text || '';
	}

	function renderAgentAction(row, postId) {
		const cell = row?.querySelector('.aiseo-agent-action');
		if (!cell) return;
		const edit = cell.querySelector('a')?.outerHTML || '';
		cell.innerHTML = '<button type="button" class="button button-primary button-small aiseo-agent-apply" data-post-id="' + String(postId) + '">Uygula</button> ' + edit;
	}

	function updateAgentScores(row, seo, read) {
		const seoCell = row?.querySelector('.aiseo-agent-seo');
		const readCell = row?.querySelector('.aiseo-agent-read');
		if (seoCell) seoCell.innerHTML = scoreBadge(seo || 0);
		if (readCell) readCell.innerHTML = scoreBadge(read || 0);
	}

	function renderEditorInternalLinks(container, suggestions) {
		if (!container) return;
		const count = (suggestions || []).length;
		const items = (suggestions || []).map((item) => {
			const title = item.target_title || item.anchor_text || item.target_url || '';
			const anchor = item.anchor_text ? ' <span class="aiseo-editor-help">(' + escapeHtml(item.anchor_text) + ')</span>' : '';
			return '<li class="is-ok">' + escapeHtml(title) + anchor + '</li>';
		}).join('');

		container.innerHTML = '<div class="aiseo-editor-suggestion">' +
			'<h4>İç Linkler Eklendi</h4>' +
			'<p class="aiseo-editor-help">En uygun ilk ' + String(count) + ' iç link editöre aktarıldı.</p>' +
			'<ul class="aiseo-editor-step-list">' + items + '</ul>' +
			'</div>';
	}

	function renderEditorTagsResult(container, tags) {
		if (!container) return;
		const items = cleanTagListLimit(tags, 8).map((tag) =>
			'<li class="is-ok">' + escapeHtml(tag) + '</li>'
		).join('');

		container.innerHTML = '<div class="aiseo-editor-suggestion">' +
			'<h4>Etiketler Güncellendi</h4>' +
			'<p class="aiseo-editor-help">Mevcut liste temiz SEO etiketleriyle değiştirildi.</p>' +
			'<ul class="aiseo-editor-step-list">' + items + '</ul>' +
			'</div>';
	}

	function initEditorPanelLegacy() {
		const panel = document.querySelector('.aiseo-editor-panel');
		if (!panel) return;

		const postId = panel.dataset.postId || Config.postId;
		const analyzeBtn = document.getElementById('aiseo-editor-analyze');
		const fixAllBtn = document.getElementById('aiseo-editor-fix-all');
		const preview = document.getElementById('aiseo-editor-preview');

		if (analyzeBtn) {
			analyzeBtn.addEventListener('click', async () => {
				UI.loading(analyzeBtn, true);
				try {
					const res = await API.analyzePost(postId, true);
					const data = res.data || {};
					document.getElementById('aiseo-editor-seo-score').textContent = data.seo_score || '—';
					document.getElementById('aiseo-editor-read-score').textContent = data.readability_score || '—';
					UI.notice('aiseo-editor-notice', 'Analiz yenilendi.', 'success');
				} catch (e) {
					UI.notice('aiseo-editor-notice', e.message || i18n.error, 'error');
				} finally {
					UI.loading(analyzeBtn, false);
				}
			});
		}

		document.querySelectorAll('.aiseo-editor-optimize').forEach((btn) => {
			btn.addEventListener('click', async () => {
				const operation = btn.dataset.operation;
				UI.loading(btn, true);
				try {
					const res = await API.optimize(postId, operation);
					renderEditorSuggestion(preview, res.data || {}, false);
				} catch (e) {
					UI.notice('aiseo-editor-notice', e.message || i18n.error, 'error');
				} finally {
					UI.loading(btn, false);
				}
			});
		});

		if (fixAllBtn) {
			fixAllBtn.addEventListener('click', async () => {
				if (!confirm('Başlık, meta, SEO ve okunabilirlik dengeli şekilde iyileştirilsin mi? Mevcut FAQ/etiketler tekrar eklenmez; değişiklikler editöre aktarılacak, kaydı siz yapacaksınız.')) return;
				UI.loading(fixAllBtn, true);
				try {
					const res = await API.fullOptimize(postId, getFullOptimizePayload(postId));
					renderEditorFullSuggestion(preview, res.data || {});
				} catch (e) {
					UI.notice('aiseo-editor-notice', e.message || i18n.error, 'error');
				} finally {
					UI.loading(fixAllBtn, false);
				}
			});
		}
	}

	function renderInlineDiff(before, after) {
		const oldTokens = diffTokens(before);
		const newTokens = diffTokens(after);
		if (!oldTokens.length && !newTokens.length) {
			return '<div class="aiseo-diff-empty">Gösterilecek değişiklik yok.</div>';
		}

		const table = Array.from({ length: oldTokens.length + 1 }, () => Array(newTokens.length + 1).fill(0));
		for (let i = oldTokens.length - 1; i >= 0; i--) {
			for (let j = newTokens.length - 1; j >= 0; j--) {
				table[i][j] = oldTokens[i] === newTokens[j]
					? table[i + 1][j + 1] + 1
					: Math.max(table[i + 1][j], table[i][j + 1]);
			}
		}

		let i = 0;
		let j = 0;
		let html = '';
		while (i < oldTokens.length && j < newTokens.length) {
			if (oldTokens[i] === newTokens[j]) {
				html += escapeHtml(oldTokens[i]);
				i++;
				j++;
			} else if (table[i + 1][j] >= table[i][j + 1]) {
				html += '<del>' + escapeHtml(oldTokens[i]) + '</del>';
				i++;
			} else {
				html += '<ins>' + escapeHtml(newTokens[j]) + '</ins>';
				j++;
			}
		}
		while (i < oldTokens.length) {
			html += '<del>' + escapeHtml(oldTokens[i]) + '</del>';
			i++;
		}
		while (j < newTokens.length) {
			html += '<ins>' + escapeHtml(newTokens[j]) + '</ins>';
			j++;
		}

		return '<div class="aiseo-diff-legend"><span class="is-removed">Silinen</span><span class="is-added">Eklenen</span></div><div class="aiseo-diff-code">' + html + '</div>';
	}

	function diffTokens(value) {
		return String(value || '').split(/(\s+|<[^>]+>|[.,;:!?()[\]{}])/g).filter((token) => token !== '');
	}

	function renderAnalysisSummary(container, data) {
		if (!container) return;
		const criteria = [...(data.seo_criteria || []), ...(data.readability_criteria || [])];
		if (!criteria.length) return;
		const errors   = criteria.filter((c) => c.status === 'error').length;
		const warnings = criteria.filter((c) => c.status === 'warning').length;
		const good     = criteria.filter((c) => c.status === 'good').length;
		const rows = criteria.map((c) => {
			const cls = c.status === 'good' ? 'is-ok' : c.status === 'warning' ? 'is-warn' : 'is-error';
			const button = c.status !== 'good'
				? '<button type="button" class="button button-small button-secondary aiseo-criterion__fix" data-criterion-id="' + escapeHtml(c.id || '') + '">Duzelt</button>'
				: '';
			return '<li class="' + cls + '"><div>' + escapeHtml(c.label + ': ' + c.message) + '</div>' + button + '</li>';
		}).join('');
		container.innerHTML = '<div class="aiseo-editor-suggestion">' +
			'<h4>Analiz Sonuçları</h4>' +
			'<p class="aiseo-editor-help">✓ ' + good + ' iyi &nbsp; △ ' + warnings + ' uyarı &nbsp; ✗ ' + errors + ' hata</p>' +
			'<ul class="aiseo-editor-step-list">' + rows + '</ul>' +
			'</div>';
	}

	function renderEditorSuggestion(container, data) {
		if (!container) return;
		editorSuggestionState = data;
		const field = data.field || 'post_content';
		const title = editorOperationLabel(data.operation || field);
		container.innerHTML = '<div class="aiseo-editor-suggestion">' +
			'<h4>' + escapeHtml(title) + '</h4>' +
			'<div class="aiseo-editor-diff-view">' + renderInlineDiff(data.before || '', data.after || '') + '</div>' +
			'<button type="button" class="button button-primary" id="aiseo-editor-apply-suggestion">Editöre Aktar</button>' +
			'</div>';

		document.getElementById('aiseo-editor-apply-suggestion')?.addEventListener('click', () => {
			applyEditorSuggestion(data);
			UI.notice('aiseo-editor-notice', 'Öneri editöre aktarıldı. Kontrol edip Güncelle ile kaydedin.', 'success');
		});
	}

	function renderEditorFullSuggestion(container, data) {
		if (!container) return;
		editorFullSuggestionState = data;
		const hasBadContent = data.content && !isAcceptableGeneratedContent(data.content);
		const okSteps = (data.steps || []).filter((step) => step.success);
		const failedSteps = (data.steps || []).filter((step) => !step.success);
		const tagPreview = Array.isArray(data.tags) && data.tags.length
			? '<p class="aiseo-editor-help">Etiketler: ' + escapeHtml(cleanTagList(data.tags).join(', ')) + '</p>'
			: '';
		container.innerHTML = '<div class="aiseo-editor-suggestion">' +
			'<h4>Tam Düzeltme Hazır</h4>' +
			'<p class="aiseo-editor-help">' + okSteps.length + ' öneri hazırlandı' + (failedSteps.length ? ', ' + failedSteps.length + ' öneri üretilemedi' : '') + '.</p>' +
			tagPreview +
			'<ul class="aiseo-editor-step-list">' + (data.steps || []).map((step) =>
				'<li class="' + (step.success ? 'is-ok' : 'is-error') + '">' + escapeHtml(editorOperationLabel(step.operation)) + '</li>'
			).join('') + '</ul>' +
			'<div class="aiseo-editor-full-diffs">' + (data.steps || []).filter((step) => step.success).map((step) =>
				'<div class="aiseo-editor-full-diff"><strong>' + escapeHtml(editorOperationLabel(step.operation)) + '</strong>' + renderInlineDiff(step.before || '', step.after || '') + '</div>'
			).join('') + '</div>' +
			'<button type="button" class="button button-primary" id="aiseo-editor-apply-full">Tamamını Editöre Aktar</button>' +
			'</div>';

		document.getElementById('aiseo-editor-apply-full')?.addEventListener('click', () => {
			if (data.content && !isAcceptableGeneratedContent(data.content)) {
				UI.notice('aiseo-editor-notice', 'AI yaniti temiz HTML degil; editore aktarilmadi.', 'error');
				return;
			}
			if (data.title) applyEditorTitle(data.title);
			if (data.content && !applyEditorContent(data.content)) return;
			if (data.meta) applyEditorMeta(data.meta, data.post_id || Config.postId);
			if (data.tags) applyEditorTags(data.tags);
			UI.notice('aiseo-editor-notice', 'Tam düzeltme editöre aktarıldı. Kontrol edip Güncelle ile kaydedin.', 'success');
		});
	}

	function applyEditorSuggestion(data) {
		const field = data.field || 'post_content';
		const after = data.after || '';
		if (!after) return;

		if (field === 'post_title') {
			applyEditorTitle(after);
		} else if (field === 'append_content') {
			applyEditorContent(getEditorContent() + '\n\n' + after);
		} else if (field === 'intro') {
			applyEditorContent(replaceIntro(getEditorContent(), after));
		} else if (field === 'post_content') {
			applyEditorContent(after);
		} else if (field === 'meta') {
			applyEditorMeta(after, data.post_id || Config.postId);
			UI.notice('aiseo-editor-notice', 'Meta önerisi editör alanlarına aktarıldı. Alan bulunamazsa saklandı.', 'success');
			return;
		}
	}

	function getEditorContent() {
		if (window.wp?.data?.select) {
			const editor = window.wp.data.select('core/editor');
			if (editor?.getEditedPostContent) {
				return editor.getEditedPostContent() || '';
			}
		}
		const tinymceEditor = window.tinymce?.get?.('content');
		if (tinymceEditor && !tinymceEditor.isHidden()) {
			return tinymceEditor.getContent() || '';
		}
		return document.getElementById('content')?.value || '';
	}

	function applyEditorContent(content) {
		content = normalizeGeneratedContent(content);
		if (!content || looksLikeBadAiDump(content)) {
			UI.notice('aiseo-editor-notice', 'AI yaniti temiz HTML degil; editore aktarilmadi.', 'error');
			return false;
		}
		if (window.wp?.data?.dispatch) {
			const editor = window.wp.data.dispatch('core/editor');
			if (editor?.editPost) {
				editor.editPost({ content });
				return true;
			}
		}
		const tinymceEditor = window.tinymce?.get?.('content');
		if (tinymceEditor && !tinymceEditor.isHidden()) {
			tinymceEditor.setContent(content);
			tinymceEditor.save();
			tinymceEditor.fire('change');
			tinymceEditor.fire('keyup');
		}
		const textarea = document.getElementById('content');
		if (textarea) {
			textarea.value = content;
			textarea.dispatchEvent(new Event('input', { bubbles: true }));
			textarea.dispatchEvent(new Event('change', { bubbles: true }));
		}
		return true;
	}

	function cleanGeneratedHtml(html) {
		return String(html || '')
			.trim()
			.replace(/^\s*```(?:html|HTML)?\s*/, '')
			.replace(/\s*```\s*$/, '')
			.replace(/^\s*(?:<!doctype\s+html[^>]*>|<html[^>]*>|<body[^>]*>)/i, '')
			.replace(/(?:<\/body>|<\/html>)\s*$/i, '')
			.trim();
	}

	function normalizeGeneratedContent(value) {
		let html = String(value || '').trim();
		if (!html) return '';

		html = html
			.replace(/^\s*```(?:json|html|HTML|JSON)?\s*/, '')
			.replace(/\s*```\s*$/, '')
			.trim();

		if (html[0] === '{') {
			try {
				const parsed = JSON.parse(html);
				if (parsed && typeof parsed.content === 'string') {
					html = parsed.content;
				}
			} catch (e) {
				return '';
			}
		}

		const articleMatch = html.match(/<article\b[^>]*data-aiseo-output=["']?1["']?[^>]*>([\s\S]*?)<\/article>/i) ||
			html.match(/<article\b[^>]*>([\s\S]*?)<\/article>/i);
		if (articleMatch) {
			html = articleMatch[1];
		}

		return cleanGeneratedHtml(html);
	}

	function isAcceptableGeneratedContent(value) {
		const content = normalizeGeneratedContent(value);
		return !!content && !looksLikeBadAiDump(content);
	}

	function looksLikeBadAiDump(value) {
		const raw = String(value || '').trim();
		if (!raw) return true;
		if (/^\s*\{\s*"(title|meta_description|content)"\s*:/i.test(raw)) return true;

		const text = raw
			.replace(/<[^>]*>/g, ' ')
			.replace(/\s+/g, ' ')
			.toLowerCase();
		const signals = [
			'we need to improve',
			'yoast criteria',
			'current content',
			'keyword density',
			'meta description',
			'need to',
			"let's",
			'actually',
			'analysis'
		];
		let hits = 0;
		signals.forEach((signal) => {
			if (text.includes(signal)) hits += 1;
		});
		return hits >= 3;
	}

	function applyEditorTitle(title) {
		if (window.wp?.data?.dispatch) {
			const editor = window.wp.data.dispatch('core/editor');
			if (editor?.editPost) {
				editor.editPost({ title });
				return;
			}
		}
		const titleInput = document.getElementById('title');
		if (titleInput) {
			titleInput.value = title;
			titleInput.dispatchEvent(new Event('input', { bubbles: true }));
			titleInput.dispatchEvent(new Event('change', { bubbles: true }));
		}
	}

	function applyEditorTags(tags) {
		const cleanTags = cleanTagList(tags);
		if (!cleanTags.length) return;

		const currentTags = getCurrentEditorTags();
		const currentKeys = new Set(currentTags.map(normalizeTag));
		const newTags = cleanTags.filter((tag) => !currentKeys.has(normalizeTag(tag))).slice(0, 3);
		if (!newTags.length) return;

		const tagString = newTags.join(', ');
		const tagInput = document.getElementById('new-tag-post_tag');
		const tagsBox = document.getElementById('tagsdiv-post_tag');
		if (tagInput && tagsBox && window.tagBox?.flushTags) {
			tagInput.value = tagString;
			window.tagBox.flushTags(tagsBox, false, 1);
			return;
		}

		const taxInput = document.getElementById('tax-input-post_tag') || document.querySelector('[name="tax_input[post_tag]"]');
		if (taxInput) {
			const current = taxInput.value ? taxInput.value.split(',').map((tag) => tag.trim()).filter(Boolean) : [];
			taxInput.value = mergeTags(current, newTags).slice(0, 12).join(', ');
			taxInput.dispatchEvent(new Event('change', { bubbles: true }));
		}
	}

	function replaceEditorTags(tags) {
		const cleanTags = cleanTagListLimit(tags, 8);
		if (!cleanTags.length) return;

		const tagString = cleanTags.join(', ');
		const tagInput = document.getElementById('new-tag-post_tag');
		const tagsBox = document.getElementById('tagsdiv-post_tag');
		const checklist = tagsBox ? tagsBox.querySelector('.tagchecklist') : null;
		const taxInput = document.getElementById('tax-input-post_tag') || document.querySelector('[name="tax_input[post_tag]"]');

		if (taxInput) {
			taxInput.value = tagString;
			taxInput.dispatchEvent(new Event('change', { bubbles: true }));
		}

		if (checklist) {
			checklist.innerHTML = cleanTags.map((tag) =>
				'<span><button type="button" class="ntdelbutton"><span class="remove-tag-icon" aria-hidden="true"></span><span class="screen-reader-text">Etiketi kaldır: ' + escapeHtml(tag) + '</span></button>&nbsp;' + escapeHtml(tag) + '</span>'
			).join('');
		}

		if (tagInput) {
			tagInput.value = '';
			tagInput.dispatchEvent(new Event('input', { bubbles: true }));
			tagInput.dispatchEvent(new Event('change', { bubbles: true }));
		}
	}

	function replaceIntro(content, intro) {
		const cleanIntro = stripParagraphWrapper(intro);
		if (/<p[^>]*>.*?<\/p>/is.test(content)) {
			return content.replace(/<p[^>]*>.*?<\/p>/is, '<p>' + escapeHtml(cleanIntro) + '</p>');
		}
		return '<p>' + escapeHtml(cleanIntro) + '</p>\n\n' + content;
	}

	function stripParagraphWrapper(value) {
		const div = document.createElement('div');
		div.innerHTML = cleanGeneratedHtml(value);
		if (div.children.length === 1 && div.firstElementChild?.tagName?.toLowerCase() === 'p') {
			return div.firstElementChild.textContent || '';
		}
		return div.textContent || String(value || '').replace(/<\/?p[^>]*>/gi, '').trim();
	}

	function applyEditorMeta(meta, postId) {
		const value = String(meta || '').trim();
		if (!value) return;

		localStorage.setItem('aiseo_pending_meta_' + (postId || Config.postId), value);

		const selectors = [
			'#yoast_wpseo_metadesc',
			'#_yoast_wpseo_metadesc',
			'textarea[name="yoast_wpseo_metadesc"]',
			'textarea[name="_yoast_wpseo_metadesc"]',
			'#rank_math_description',
			'textarea[name="rank_math_description"]',
			'#aioseo-post-settings-description',
			'textarea[name="aioseo_description"]'
		];

		let applied = false;
		selectors.forEach((selector) => {
			document.querySelectorAll(selector).forEach((field) => {
				if ('value' in field) {
					field.value = value;
					field.dispatchEvent(new Event('input', { bubbles: true }));
					field.dispatchEvent(new Event('change', { bubbles: true }));
					applied = true;
				}
			});
		});

		if (!applied && window.wp?.data?.dispatch) {
			const editor = window.wp.data.dispatch('core/editor');
			if (editor?.editPost) {
				editor.editPost({ meta: { _aiseo_meta_description: value } });
			}
		}
	}

	function cleanTagList(tags) {
		return cleanTagListLimit(tags, 3);
	}

	function cleanTagListLimit(tags, limit) {
		const clean = [];
		const seen = new Set();
		(Array.isArray(tags) ? tags : []).forEach((tag) => {
			const value = String(tag || '').replace(/[#,]/g, ' ').replace(/\s+/g, ' ').trim();
			const key = normalizeTag(value);
			if (value.length < 4 || !key || seen.has(key)) return;
			seen.add(key);
			clean.push(value);
		});
		return clean.slice(0, limit || 3);
	}

	function getCurrentEditorTags() {
		const tags = [];
		document.querySelectorAll('#tagsdiv-post_tag .tagchecklist .ntdelbutton, #tagsdiv-post_tag .tagchecklist button').forEach((button) => {
			const holder = button.parentElement?.cloneNode(true);
			if (holder && holder.querySelectorAll) {
				holder.querySelectorAll('button, .ntdelbutton, .screen-reader-text').forEach((node) => node.remove());
			}
			const tag = (holder?.textContent || '').replace(/\s+/g, ' ').trim();
			if (tag) tags.push(tag);
		});

		const taxInput = document.getElementById('tax-input-post_tag') || document.querySelector('[name="tax_input[post_tag]"]');
		if (taxInput?.value) {
			tags.push(...taxInput.value.split(',').map((tag) => tag.trim()).filter(Boolean));
		}

		return tags;
	}

	function normalizeTag(tag) {
		return String(tag || '').toLocaleLowerCase('tr-TR').replace(/[#,]/g, ' ').replace(/\s+/g, ' ').trim();
	}

	function mergeTags(current, additions) {
		const seen = new Set();
		const merged = [];
		current.concat(additions).forEach((tag) => {
			const clean = String(tag || '').replace(/[#,]/g, ' ').replace(/\s+/g, ' ').trim();
			const key = normalizeTag(clean);
			if (!key || seen.has(key)) return;
			seen.add(key);
			merged.push(clean);
		});
		return merged;
	}

	function editorOperationLabel(operation) {
		if (operation === 'full_content_optimization') return 'Tam Icerik Revizyonu';
		return {
			optimize_title: 'Başlık İyileştirme',
			optimize_meta: 'Meta Açıklama',
			improve_intro: 'Giriş Paragrafı',
			improve_readability: 'Okunabilirlik',
			improve_keyword_density: 'Keyword Dağılımı',
			add_faq: 'FAQ Ekleme',
			improve_conclusion: 'Sonuç Bölümü',
			regenerate_article: 'Baştan Oluşturma',
			post_content: 'İçerik',
			append_content: 'İçeriğe Ekleme',
			meta: 'Meta Açıklama',
		}[operation] || 'AI Önerisi';
	}

	function initPremiumUi() {
		document.querySelectorAll('.aiseo-compact-toggle').forEach((btn) => {
			if (btn._aiseoCompactBound) return;
			btn._aiseoCompactBound = true;
			btn.addEventListener('click', () => {
				const target = document.getElementById(btn.dataset.target || '');
				if (!target) return;
				target.classList.toggle('aiseo-compact');
				btn.classList.toggle('is-active', target.classList.contains('aiseo-compact'));
			});
		});

		document.querySelectorAll('[data-bulk-filter-chip]').forEach((btn) => {
			if (btn._aiseoFilterChipBound) return;
			btn._aiseoFilterChipBound = true;
			btn.addEventListener('click', () => {
				const filter = document.getElementById('aiseo-bulk-filter');
				if (!filter) return;
				filter.value = btn.dataset.bulkFilterChip || '';
				filter.dispatchEvent(new Event('change'));
				document.querySelectorAll('[data-bulk-filter-chip]').forEach((chip) => chip.classList.remove('is-active'));
				btn.classList.add('is-active');
			});
		});

		const presetWrap = document.querySelector('[data-generator-presets]');
		if (presetWrap && !presetWrap._aiseoPresetBound) {
			presetWrap._aiseoPresetBound = true;
			presetWrap.querySelectorAll('[data-target-words]').forEach((btn) => {
				btn.addEventListener('click', () => {
					const wordSelect = document.getElementById('aiseo-gen-word-count');
					const tokenHint = document.querySelector('[data-generator-token-estimate]');
					const words = parseInt(btn.dataset.targetWords || '0', 10);
					if (wordSelect && words) {
						wordSelect.value = String(words);
						wordSelect.dispatchEvent(new Event('change'));
					}
					if (tokenHint && words) {
						tokenHint.textContent = 'Tahmini taslak seviyesi: ' + words + ' kelime';
					}
					presetWrap.querySelectorAll('.aiseo-chip').forEach((chip) => chip.classList.remove('is-active'));
					btn.classList.add('is-active');
				});
			});
		}
	}

	function initSeoTitleFix() {
		const allCheck = document.getElementById('aiseo-stf-select-all');
		const allCheckHeader = document.getElementById('aiseo-stf-select-all-header');
		const fixSelectedBtn = document.getElementById('aiseo-stf-fix-selected');
		if (!fixSelectedBtn) return;

		async function fixPost(postId) {
			const statusEl = document.getElementById('aiseo-stf-row-status-' + postId);
			const titleEl = document.getElementById('aiseo-stf-title-' + postId);
			const btn = document.querySelector('.aiseo-stf-fix-btn[data-post-id="' + postId + '"]');
			if (statusEl) statusEl.textContent = '...';
			if (btn) btn.disabled = true;

			try {
				const res = await fetch(restUrl + 'aiseo/v1/seo-title/fix/' + postId, {
					method: 'POST',
					headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
				});
				const data = await res.json();
				if (data.success && data.seo_title) {
					if (titleEl) titleEl.textContent = data.seo_title;
					if (statusEl) statusEl.textContent = 'Done';
				} else {
					if (statusEl) statusEl.textContent = data.message || '--';
					if (btn) btn.disabled = false;
				}
			} catch (e) {
				if (statusEl) statusEl.textContent = 'Error';
				if (btn) btn.disabled = false;
			}
		}

		function syncSelectAll() {
			const checkboxes = document.querySelectorAll('.aiseo-stf-checkbox');
			const checked = document.querySelectorAll('.aiseo-stf-checkbox:checked');
			const allSelected = checkboxes.length > 0 && checked.length === checkboxes.length;
			if (allCheck) allCheck.checked = allSelected;
			if (allCheckHeader) allCheckHeader.checked = allSelected;
			fixSelectedBtn.disabled = checked.length === 0;
		}

		document.querySelectorAll('.aiseo-stf-fix-btn').forEach((btn) => {
			btn.addEventListener('click', () => fixPost(btn.dataset.postId));
		});
		document.querySelectorAll('.aiseo-stf-checkbox').forEach((cb) => {
			cb.addEventListener('change', syncSelectAll);
		});
		[allCheck, allCheckHeader].forEach((el) => {
			if (!el) return;
			el.addEventListener('change', () => {
				document.querySelectorAll('.aiseo-stf-checkbox').forEach((cb) => {
					cb.checked = el.checked;
				});
				syncSelectAll();
			});
		});

		fixSelectedBtn.addEventListener('click', async () => {
			const selected = Array.from(document.querySelectorAll('.aiseo-stf-checkbox:checked')).map((cb) => cb.value);
			if (!selected.length) return;
			fixSelectedBtn.disabled = true;
			const progressWrap = document.getElementById('aiseo-stf-progress-wrap');
			const progressBar = document.getElementById('aiseo-stf-progress');
			const statusEl = document.getElementById('aiseo-stf-status');
			if (progressWrap) progressWrap.style.display = 'block';
			let done = 0;
			for (const postId of selected) {
				if (statusEl) statusEl.textContent = done + ' / ' + selected.length;
				await fixPost(postId);
				done += 1;
				if (progressBar) progressBar.style.width = Math.round((done / selected.length) * 100) + '%';
			}
			if (statusEl) statusEl.textContent = selected.length + ' yazi islendi.';
			fixSelectedBtn.disabled = false;
		});
	}

	/* ------------------------------------------------------------------ */
	/* Settings Page                                                        */
	/* ------------------------------------------------------------------ */
	function initSettings() {
		const saveBtn    = document.getElementById('aiseo-save-settings');
		const testBtn    = document.getElementById('aiseo-test-key');
		const toggleBtn  = document.getElementById('aiseo-toggle-key');
		const keyInput   = document.getElementById('aiseo-api-key');
		const navItems   = document.querySelectorAll('[data-settings-panel]');

		if (toggleBtn && keyInput) {
			toggleBtn.addEventListener('click', () => {
				const type = keyInput.type === 'password' ? 'text' : 'password';
				keyInput.type = type;
			});
		}

		navItems.forEach((item) => {
			item.addEventListener('click', () => {
				const key = item.dataset.settingsPanel;
				navItems.forEach((nav) => nav.classList.toggle('is-active', nav === item));
				document.querySelectorAll('[data-settings-content]').forEach((panel) => {
					const active = panel.dataset.settingsContent === key;
					panel.classList.toggle('is-active', active);
					panel.hidden = !active;
				});
			});
		});

		if (testBtn && keyInput) {
			testBtn.addEventListener('click', async () => {
				UI.loading(testBtn, true);
				try {
					const res = await API.testKey(collectSettings());
					UI.notice('aiseo-settings-notice', res.message, res.data?.connected ? 'success' : 'error');
				} catch (e) {
					UI.notice('aiseo-settings-notice', i18n.testKeyFail || 'Bağlantı başarısız.', 'error');
				} finally {
					UI.loading(testBtn, false);
				}
			});
		}

		if (saveBtn) {
			saveBtn.addEventListener('click', async () => {
				UI.loading(saveBtn, true);
				const data = collectSettings();
				try {
					await API.saveSettings(data);
					UI.notice('aiseo-settings-notice', 'Ayarlar kaydedildi.', 'success');
				} catch (e) {
					UI.notice('aiseo-settings-notice', e.message || i18n.error, 'error');
				} finally {
					UI.loading(saveBtn, false);
				}
			});
		}
	}

	function collectSettings() {
		const get = (id) => document.querySelector('[name="' + id + '"]');
		const val = (id) => get(id)?.value;
		const chk = (id) => get(id)?.checked ? 1 : 0;

		const raw = {
			ai_provider:         document.getElementById('aiseo-provider')?.value          || 'openai',
			openai_api_key:      document.getElementById('aiseo-api-key')?.value?.trim() || '',
			openai_model:        document.getElementById('aiseo-model')?.value            || '',
			ai_base_url:         document.getElementById('aiseo-base-url')?.value?.trim() || '',
			quality_mode:        document.getElementById('aiseo-quality-mode')?.value     || 'balanced',
			max_tokens:          parseInt(val('max_tokens'))          || 2000,
			monthly_token_limit: parseInt(val('monthly_token_limit')) || 500000,
			daily_limit:         parseInt(val('daily_limit'))         || 100,
			default_language:    val('default_language')              || 'tr',
			default_tone:        val('default_tone')                  || 'professional',
			analysis_cache_ttl:  parseInt(val('analysis_cache_ttl'))  || 86400,
			enable_logging:      chk('enable_logging'),
			enable_yoast_sync:   chk('enable_yoast_sync'),
		};

		if (raw.openai_api_key.includes('*')) {
			delete raw.openai_api_key;
		}
		return raw;
	}

	/* ------------------------------------------------------------------ */
	/* GitHub Update Page                                                   */
	/* ------------------------------------------------------------------ */
	function initGithub() {
		const checkBtn = document.getElementById('aiseo-check-github-version');
		const resultEl = document.getElementById('aiseo-github-version-result');

		if (!checkBtn || !resultEl) return;

		checkBtn.addEventListener('click', async () => {
			UI.loading(checkBtn, true);
			resultEl.textContent = '';

			const data = new FormData();
			data.append('action', 'aiseo_check_github_version');
			data.append('nonce', githubNonce);

			try {
				const res = await fetch(Config.ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					body: data,
				});
				const text = await res.text();
				let json = {};
				try {
					json = text ? JSON.parse(text) : {};
				} catch (e) {
					json = { success: false, data: { message: text.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim() } };
				}
				resultEl.textContent = json.data?.message || json.message || (json.success ? 'GitHub bağlantısı başarılı.' : 'GitHub sürümü okunamadı.');
				resultEl.className = 'aiseo-muted-inline ' + (json.success ? 'aiseo-text-success' : 'aiseo-text-error');
			} catch (e) {
				resultEl.textContent = 'GitHub sürümü okunamadı.';
				resultEl.className = 'aiseo-muted-inline aiseo-text-error';
			} finally {
				UI.loading(checkBtn, false);
			}
		});
	}

	/* ------------------------------------------------------------------ */
	/* Dashboard                                                            */
	/* ------------------------------------------------------------------ */
	function initDashboard() {
		const refreshBtn = document.getElementById('aiseo-refresh-all-analyses');
		if (!refreshBtn) return;

		refreshBtn.addEventListener('click', async () => {
			let postIds = [];
			try {
				postIds = JSON.parse(refreshBtn.dataset.postIds || '[]')
					.map((id) => parseInt(id))
					.filter((id) => Number.isFinite(id) && id > 0);
			} catch (e) {
				postIds = [];
			}

			if (!postIds.length) {
				UI.notice('aiseo-dashboard-notice', 'Analiz edilecek yayinlanmis yazi bulunamadi.', 'warning');
				return;
			}

			if (!confirm(postIds.length + ' yazinin analizini yenileyeyim mi? Bu islem icerik sayisina gore zaman alabilir.')) return;

			const progressWrap = document.getElementById('aiseo-dashboard-refresh-progress');
			const progressBar = document.getElementById('aiseo-dashboard-progress-bar');
			const statusEl = document.getElementById('aiseo-dashboard-progress-status');
			const batchSize = 10;
			let processed = 0;
			let succeeded = 0;
			let failed = 0;

			UI.loading(refreshBtn, true);
			UI.spin(progressWrap, true);
			if (progressBar) progressBar.style.width = '0%';
			if (statusEl) statusEl.textContent = '0 / ' + postIds.length;

			for (let i = 0; i < postIds.length; i += batchSize) {
				const batch = postIds.slice(i, i + batchSize);
				try {
					const res = await API.bulkAnalyze(batch);
					const results = res.data?.results || [];
					results.forEach((item) => {
						if (item.success) succeeded++;
						else failed++;
					});
					processed += batch.length;
				} catch (e) {
					processed += batch.length;
					failed += batch.length;
				}

				const pct = Math.round((processed / postIds.length) * 100);
				if (progressBar) progressBar.style.width = pct + '%';
				if (statusEl) statusEl.textContent = processed + ' / ' + postIds.length;
			}

			UI.loading(refreshBtn, false);
			UI.notice('aiseo-dashboard-notice', 'Analiz yenileme tamamlandi. Basarili: ' + succeeded + ', hata: ' + failed + '.', failed ? 'warning' : 'success');
			setTimeout(() => window.location.reload(), 900);
		});
	}

	function initBulkAnalysis() {
		const startBtn       = document.getElementById('aiseo-bulk-start');
		const selectAll      = document.getElementById('aiseo-select-all');
		const selectAllH     = document.getElementById('aiseo-select-all-header');
		const filter         = document.getElementById('aiseo-bulk-filter');
		const search         = document.getElementById('aiseo-bulk-search');
		const visibleCountEl = document.getElementById('aiseo-bulk-visible-count');
		const emptyStateEl   = document.getElementById('aiseo-bulk-empty-state');
		const rows           = Array.from(document.querySelectorAll('#aiseo-bulk-table tbody tr[data-post-id]'));

		const applyFilters = () => {
			const scoreValue = (filter?.value || '').trim();
			const query = (search?.value || '').trim().toLowerCase();
			let visible = 0;

			rows.forEach((row) => {
				const color = row.dataset.scoreColor || 'none';
				const title = row.dataset.title || '';
				const show = (!scoreValue || color === scoreValue) && (!query || title.includes(query));
				row.style.display = show ? '' : 'none';
				if (show) visible++;
			});

			if (visibleCountEl) visibleCountEl.textContent = visible + ' / ' + rows.length + ' yazi';
			if (emptyStateEl) emptyStateEl.style.display = visible ? 'none' : '';
		};

		if (selectAll) {
			selectAll.addEventListener('change', () => {
				document.querySelectorAll('.aiseo-post-select').forEach((cb) => {
					cb.checked = selectAll.checked;
				});
			});
		}
		if (selectAllH) {
			selectAllH.addEventListener('change', () => {
				document.querySelectorAll('.aiseo-post-select').forEach((cb) => {
					cb.checked = selectAllH.checked;
				});
			});
		}
		if (filter) {
			const initialFilter = new URLSearchParams(window.location.search).get('score_filter');
			if (initialFilter) filter.value = initialFilter;
			filter.addEventListener('change', applyFilters);
		}
		if (search) search.addEventListener('input', applyFilters);

		applyFilters();
		if (!startBtn) return;

		startBtn.addEventListener('click', async () => {
			const selected = uniqueIds(Array.from(document.querySelectorAll('.aiseo-post-select:checked')).map((cb) => cb.value));
			if (!selected.length) {
				UI.notice('aiseo-bulk-notice', i18n.selectPosts || 'En az bir yazi secin.', 'warning');
				return;
			}

			const result = await runBulkAnalyzeQueue(selected, {
				button: startBtn,
				progressWrap: document.getElementById('aiseo-bulk-progress-wrap'),
				progressBar: document.getElementById('aiseo-bulk-progress'),
				statusEl: document.getElementById('aiseo-bulk-status'),
				statusPrefix: 'Analiz',
				onResult: (postId, data) => {
					if (data?.success) {
						UI.updateScoreBadge(postId, data.seo_score || 0, data.readability_score || 0);
						updateBulkRow(data);
					}
				},
			});

			applyFilters();
			UI.notice('aiseo-bulk-notice', 'Toplu analiz tamamlandi. Basarili: ' + result.succeeded + ', hata: ' + result.failed + '.', result.failed ? 'warning' : 'success');
		});
	}

	async function runAgentOptimizationQueue(postIds, triggerBtn) {
		const ids = uniqueIds(postIds);
		if (!ids.length) {
			UI.notice('aiseo-agent-notice', 'En az bir yazi secin.', 'warning');
			return null;
		}

		const progressWrap = document.getElementById('aiseo-agent-progress-wrap');
		const progressBar = document.getElementById('aiseo-agent-progress');
		const statusEl = document.getElementById('aiseo-agent-status');
		const targetSeo = parseInt(document.getElementById('aiseo-agent-target-seo')?.value) || 80;
		const targetRead = parseInt(document.getElementById('aiseo-agent-target-read')?.value) || 75;
		let done = 0;
		let ready = 0;
		let skipped = 0;
		let failed = 0;

		if (triggerBtn) UI.loading(triggerBtn, true);
		UI.spin(progressWrap, true);
		setProgress(progressBar, statusEl, 0, ids.length, 'Oneri');

		try {
			for (const postId of ids) {
				const row = document.querySelector('#aiseo-agent-table tr[data-post-id="' + postId + '"]');
				setAgentState(row, 'Analiz ediliyor...');
				try {
					const res = await API.agentOptimize({
						post_id: postId,
						target_seo: targetSeo,
						target_readability: targetRead,
					});
					const data = res.data || {};
					if (data.skipped) {
						skipped++;
						setAgentState(row, data.reason || 'Hedefte');
					} else {
						ready++;
						agentProposals.set(postId, data);
						setAgentState(row, 'Oneri hazir');
						renderAgentAction(row, postId);
					}
					if (data.before) updateAgentScores(row, data.before.seo_score, data.before.readability_score);
				} catch (e) {
					failed++;
					setAgentState(row, e.message || 'Hata');
				}
				done++;
				setProgress(progressBar, statusEl, done, ids.length, 'Oneri');
			}
		} finally {
			if (triggerBtn) UI.loading(triggerBtn, false);
		}

		UI.notice('aiseo-agent-notice', 'Otomatik iyilestirme taramasi tamamlandi. Hazir: ' + ready + ', hedefte: ' + skipped + ', hata: ' + failed + '.', failed ? 'warning' : 'success');
		return { ready, skipped, failed };
	}

	function initAgentOptimizer() {
		const startBtn = document.getElementById('aiseo-agent-start');
		const refreshAllBtn = document.getElementById('aiseo-agent-refresh-all');
		const selectAll = document.getElementById('aiseo-agent-select-all');
		const selectAllHeader = document.getElementById('aiseo-agent-select-all-header');
		if (!startBtn) return;

		const toggleAll = (checked) => {
			document.querySelectorAll('.aiseo-agent-select').forEach((cb) => {
				cb.checked = checked;
			});
		};
		if (selectAll) selectAll.addEventListener('change', () => toggleAll(selectAll.checked));
		if (selectAllHeader) selectAllHeader.addEventListener('change', () => toggleAll(selectAllHeader.checked));

		startBtn.addEventListener('click', async () => {
			const selected = uniqueIds(Array.from(document.querySelectorAll('.aiseo-agent-select:checked')).map((cb) => cb.value));
			await runAgentOptimizationQueue(selected, startBtn);
		});

		if (refreshAllBtn) {
			refreshAllBtn.addEventListener('click', async () => {
				const allIds = uniqueIds(Array.from(document.querySelectorAll('#aiseo-agent-table tr[data-post-id]')).map((row) => row.dataset.postId));
				await runAgentOptimizationQueue(allIds, refreshAllBtn);
			});
		}

		document.addEventListener('click', async (event) => {
			const btn = event.target.closest('.aiseo-agent-apply');
			if (!btn) return;
			event.preventDefault();
			const postId = parseInt(btn.dataset.postId);
			const proposal = agentProposals.get(postId);
			if (!proposal) return;
			if (!confirm('Bu oneri yaziya uygulansin mi? Uygulama oncesinde revision olusturulur.')) return;

			const row = btn.closest('tr');
			UI.loading(btn, true);
			setAgentState(row, 'Uygulaniyor...');
			try {
				const res = await API.agentApply({
					post_id: postId,
					title: proposal.title || '',
					content: proposal.content || '',
					meta: proposal.meta || '',
					tags: proposal.tags || [],
				});
				const after = res.data?.after || {};
				updateAgentScores(row, after.seo_score || 0, after.readability_score || 0);
				setAgentState(row, 'Uygulandi');
				btn.remove();
				UI.notice('aiseo-agent-notice', 'Oneri uygulandi ve yazi yeniden analiz edildi.', 'success');
			} catch (e) {
				setAgentState(row, e.message || 'Uygulama hatasi');
				UI.notice('aiseo-agent-notice', e.message || i18n.error, 'error');
			} finally {
				UI.loading(btn, false);
			}
		});
	}

	function initDashboard() {
		const refreshBtn = document.getElementById('aiseo-refresh-all-analyses');
		if (!refreshBtn) return;

		refreshBtn.addEventListener('click', async () => {
			const postIds = uniqueIds(Config.dashboardPostIds || []);
			if (!postIds.length) {
				UI.notice('aiseo-dashboard-notice', 'Analiz edilecek yayinlanmis yazi bulunamadi.', 'warning');
				return;
			}

			const result = await runBulkAnalyzeQueue(postIds, {
				button: refreshBtn,
				progressWrap: document.getElementById('aiseo-dashboard-refresh-progress'),
				progressBar: document.getElementById('aiseo-dashboard-progress-bar'),
				statusEl: document.getElementById('aiseo-dashboard-progress-status'),
				statusPrefix: 'Dashboard',
			});

			UI.notice('aiseo-dashboard-notice', 'Tum analizler yenilendi. Basarili: ' + result.succeeded + ', hata: ' + result.failed + '.', result.failed ? 'warning' : 'success');
			setTimeout(() => window.location.reload(), 1200);
		});
	}

	/* ------------------------------------------------------------------ */
	/* Auto Publisher                                                       */
	/* ------------------------------------------------------------------ */
	function initAutoPublisher() {
		const saveBtn    = document.getElementById('aiseo-ap-save');
		const triggerBtn = document.getElementById('aiseo-ap-trigger');
		const refreshBtn = document.getElementById('aiseo-ap-refresh-queue');
		const enabledEl  = document.getElementById('aiseo-ap-enabled');
		const statusLbl  = document.getElementById('aiseo-ap-status-label');

		function getFormData() {
			const categoryEls = document.querySelectorAll('#aiseo-ap-categories option:checked');
			return {
				enabled:                document.getElementById('aiseo-ap-enabled')?.checked || false,
				interval_hours:         parseFloat(document.getElementById('aiseo-ap-interval')?.value) || 24,
				min_seo_score:          parseInt(document.getElementById('aiseo-ap-min-seo')?.value) || 70,
				min_readability_score:  parseInt(document.getElementById('aiseo-ap-min-read')?.value) || 60,
				category_ids:           Array.from(categoryEls).map((o) => parseInt(o.value)).filter(Boolean),
				internal_links_count:   parseInt(document.getElementById('aiseo-ap-links')?.value) || 3,
				target_words:           parseInt(document.getElementById('aiseo-ap-words')?.value) || 1000,
				tone:                   document.getElementById('aiseo-ap-tone')?.value || 'professional',
				include_faq:            document.getElementById('aiseo-ap-faq')?.checked || false,
				auto_generate:          document.getElementById('aiseo-ap-auto-generate')?.checked || false,
				optimize_before_publish: document.getElementById('aiseo-ap-optimize')?.checked || false,
			};
		}

		if (enabledEl && statusLbl) {
			enabledEl.addEventListener('change', () => {
				statusLbl.textContent = enabledEl.checked ? 'Aktif' : 'Pasif';
				statusLbl.className   = 'aiseo-ap-status-label ' + (enabledEl.checked ? 'active' : 'inactive');
			});
		}

		if (saveBtn) {
			saveBtn.addEventListener('click', async () => {
				UI.loading(saveBtn, true);
				try {
					const res = await API.saveAutoPublisherSettings(getFormData());
					const d = res.data || {};
					const nextRunEl = document.querySelector('#aiseo-ap-interval + p.description');
					if (nextRunEl && d.next_run) {
						nextRunEl.textContent = 'Sonraki çalışma: ' + d.next_run;
					} else if (nextRunEl) {
						nextRunEl.textContent = 'Henüz zamanlanmamış.';
					}
					UI.notice('aiseo-ap-notice', res.message || 'Ayarlar kaydedildi.', 'success');
				} catch (e) {
					UI.notice('aiseo-ap-notice', e.message || i18n.error, 'error');
				} finally {
					UI.loading(saveBtn, false);
				}
			});
		}

		if (triggerBtn) {
			triggerBtn.addEventListener('click', async () => {
				if (!confirm('Kuyruktan bir taslak şimdi işlenip yayınlansın mı? Bu işlem birkaç dakika sürebilir.')) return;
				UI.loading(triggerBtn, true);
				UI.notice('aiseo-ap-notice', 'İşleniyor, lütfen bekleyin...', 'info');
				try {
					const res = await API.triggerAutoPublisher();
					const d = res.data || {};
					const msg = res.message || 'Tamamlandı.';
					UI.notice('aiseo-ap-notice', msg + (d.seo_score ? ' (SEO: ' + d.seo_score + ', Okunabilirlik: ' + d.readability_score + ')' : ''), 'success');
					setTimeout(() => window.location.reload(), 2000);
				} catch (e) {
					UI.notice('aiseo-ap-notice', e.message || i18n.error, 'error');
				} finally {
					UI.loading(triggerBtn, false);
				}
			});
		}

		if (refreshBtn) {
			refreshBtn.addEventListener('click', async () => {
				UI.loading(refreshBtn, true);
				try {
					const res = await API.refreshAutoPublisherQueue();
					const queue = res.data?.queue || [];
					const tbody = document.getElementById('aiseo-ap-queue-body');
					const wrap  = document.getElementById('aiseo-ap-queue-wrap');
					if (!tbody && wrap) {
						if (queue.length === 0) {
							wrap.innerHTML = '<p class="aiseo-ap-empty">Kuyrukta taslak yok.</p>';
						} else {
							wrap.innerHTML = buildQueueTable(queue);
							bindSkipButtons();
						}
					} else if (tbody) {
						if (queue.length === 0) {
							const table = tbody.closest('table');
							if (table) table.outerHTML = '<p class="aiseo-ap-empty">Kuyrukta taslak yok.</p>';
						} else {
							tbody.innerHTML = queue.map(queueRowHtml).join('');
							bindSkipButtons();
						}
					}
				} catch (e) {
					UI.notice('aiseo-ap-notice', e.message || i18n.error, 'error');
				} finally {
					UI.loading(refreshBtn, false);
				}
			});
		}

		bindSkipButtons();

		function bindSkipButtons() {
			document.querySelectorAll('.aiseo-ap-skip-btn').forEach((btn) => {
				if (btn._bound) return;
				btn._bound = true;
				btn.addEventListener('click', async () => {
					const postId = btn.dataset.postId;
					UI.loading(btn, true);
					try {
						await API.skipAutoPublisherPost(postId, true);
						const row = btn.closest('tr');
						if (row) row.remove();
					} catch (e) {
						UI.notice('aiseo-ap-notice', e.message || i18n.error, 'error');
					} finally {
						UI.loading(btn, false);
					}
				});
			});
		}

		function queueRowHtml(item) {
			const cats  = (item.categories || []).join(', ') || '—';
			const fail  = item.score_fail
				? '<span class="aiseo-badge aiseo-badge--red" title="' + escapeHtml(item.score_fail) + '">Puan Düşük</span>'
				: '<span class="aiseo-badge aiseo-badge--none">Bekliyor</span>';
			return '<tr data-post-id="' + escapeHtml(item.id) + '">' +
				'<td><a href="' + escapeHtml(item.edit_url) + '">' + escapeHtml(item.title) + '</a></td>' +
				'<td>' + escapeHtml(cats) + '</td>' +
				'<td>' + escapeHtml(item.attempts || '0') + '</td>' +
				'<td>' + fail + '</td>' +
				'<td><button type="button" class="button button-small aiseo-ap-skip-btn" data-post-id="' + escapeHtml(item.id) + '">Atla</button></td>' +
				'</tr>';
		}

		function buildQueueTable(queue) {
			return '<table class="aiseo-table wp-list-table widefat fixed striped">' +
				'<thead><tr><th>Başlık</th><th>Kategori</th><th>Deneme</th><th>Durum</th><th>İşlem</th></tr></thead>' +
				'<tbody id="aiseo-ap-queue-body">' + queue.map(queueRowHtml).join('') + '</tbody></table>';
		}
	}

	/* ------------------------------------------------------------------ */
	/* Router                                                               */
	/* ------------------------------------------------------------------ */
	function init() {
		const page = Config.currentPage || '';

		initModalClose();
		initPremiumUi();

		if (page === 'aiseo-posts' || page === '') {
			initPostListAnalyze();
			initPostDetailOptimize();
		}
		if (page === 'aiseo-bulk') {
			initBulkAnalysis();
		}
		if (page === 'aiseo-agent') {
			initAgentOptimizer();
		}
		if (page === 'aiseo-generator') {
			initArticleGenerator();
		}
		if (page === 'aiseo-links') {
			initInternalLinks();
		}
		if (page === 'aiseo-auto-publisher') {
			initAutoPublisherEnhanced();
		}
		if (page === 'aiseo-settings') {
			initSettings();
		}
		if (page === 'aiseo-github') {
			initGithub();
		}
		if (page === 'aiseo-dashboard') {
			initDashboard();
		}
		if (page === 'aiseo-seo-title-fix') {
			initSeoTitleFix();
		}

		// Also init optimize buttons on any page (post detail is embedded in posts page)
		initPostDetailOptimize();
		initPendingLinkContent();
		initEditorPanel();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

	window.AISeo = { API, UI };
})();
