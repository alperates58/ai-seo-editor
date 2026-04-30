/* AI SEO Editor — Admin JavaScript */
/* globals AISeoConfig */

(function () {
	'use strict';

	const Config  = window.AISeoConfig || {};
	const i18n    = Config.i18n || {};
	const restUrl = Config.restUrl || '';
	const nonce   = Config.nonce || '';

	/* ------------------------------------------------------------------ */
	/* API Module                                                           */
	/* ------------------------------------------------------------------ */
	const API = {
		async request(endpoint, method, body) {
			const options = {
				method:  method || 'GET',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
			};
			if (body) options.body = JSON.stringify(body);
			const res = await fetch(restUrl + 'aiseo/v1' + endpoint, options);
			const json = await res.json();
			if (!res.ok) throw json;
			return json;
		},
		analyzePost:      (pid, force) => API.request('/analyze/' + pid, 'POST', { force: !!force }),
		getAnalysis:      (pid)        => API.request('/analyze/' + pid),
		optimize:         (pid, op)    => API.request('/optimize', 'POST', { post_id: pid, operation: op }),
		applyOptimize:    (data)       => API.request('/optimize/apply', 'POST', data),
		bulkAnalyze:      (ids)        => API.request('/bulk-analyze', 'POST', { post_ids: ids }),
		generateArticle:  (params)     => API.request('/generate', 'POST', params),
		createDraft:      (data)       => API.request('/generate/create-draft', 'POST', data),
		getLinks:         (pid)        => API.request('/links/' + pid),
		computeLinks:     (pid)        => API.request('/links/' + pid + '/compute', 'POST'),
		applyLinks:       (pid, ids)   => API.request('/links/apply', 'POST', { post_id: pid, suggestion_ids: ids }),
		getSettings:      ()           => API.request('/settings'),
		saveSettings:     (data)       => API.request('/settings', 'POST', data),
		testKey:          (key)        => API.request('/settings/test-key', 'POST', { api_key: key }),
		getDashboard:     ()           => API.request('/dashboard'),
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
						const seoCell  = row.querySelector('.aiseo-score-cell');
						if (seoCell) seoCell.innerHTML = scoreBadge(data.seo_score || 0);
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
	function initPostDetailOptimize() {
		const detailWrap = document.getElementById('aiseo-post-detail');
		if (!detailWrap) return;
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
			filter.addEventListener('change', () => {
				const val = filter.value;
				document.querySelectorAll('#aiseo-bulk-table tbody tr').forEach((row) => {
					if (!val) { row.style.display = ''; return; }
					const color = row.dataset.scoreColor || 'none';
					row.style.display = (color === val) ? '' : 'none';
				});
			});
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
			const batchSize = 5;

			for (let i = 0; i < selected.length; i += batchSize) {
				const batch = selected.slice(i, i + batchSize);
				try {
					const res = await API.bulkAnalyze(batch);
					(res.data?.results || []).forEach((r) => {
						UI.updateScoreBadge(r.post_id, r.seo_score, r.readability_score);
						processed++;
					});
				} catch (e) {
					processed += batch.length;
				}
				const pct = Math.round((processed / total) * 100);
				if (progressBar) progressBar.style.width = pct + '%';
				if (statusEl) statusEl.textContent = processed + ' / ' + total;
			}

			UI.loading(startBtn, false);
			UI.notice('aiseo-bulk-notice', i18n.bulkDone || 'Toplu analiz tamamlandı!', 'success');
		});
	}

	/* ------------------------------------------------------------------ */
	/* Article Generator                                                    */
	/* ------------------------------------------------------------------ */
	let lastGenerationResult = null;

	function initArticleGenerator() {
		const genBtn      = document.getElementById('aiseo-generate-btn');
		const draftBtn    = document.getElementById('aiseo-create-draft-btn');
		const loadingEl   = document.getElementById('aiseo-generate-loading');
		const previewCard = document.getElementById('aiseo-preview-card');

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
				};

				try {
					const res = await API.generateArticle(params);
					lastGenerationResult = res.data || {};
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
					.map((cb) => parseInt(cb.value));

				if (!postId || !selected.length) {
					UI.notice('aiseo-links-notice', 'Önce yazı seçin ve link önerilerini işaretleyin.', 'warning');
					return;
				}

				if (!confirm('Seçili iç linkleri yazıya eklemek istiyor musunuz? Revision otomatik oluşturulacak.')) return;

				UI.loading(applyBtn, true);
				try {
					await API.applyLinks(postId, selected);
					UI.notice('aiseo-links-notice', 'İç linkler başarıyla eklendi!', 'success');
					if (resultsEl) resultsEl.style.display = 'none';
				} catch (e) {
					UI.notice('aiseo-links-notice', e.message || i18n.error, 'error');
				} finally {
					UI.loading(applyBtn, false);
				}
			});
		}
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

	/* ------------------------------------------------------------------ */
	/* Settings Page                                                        */
	/* ------------------------------------------------------------------ */
	function initSettings() {
		const saveBtn    = document.getElementById('aiseo-save-settings');
		const testBtn    = document.getElementById('aiseo-test-key');
		const toggleBtn  = document.getElementById('aiseo-toggle-key');
		const keyInput   = document.getElementById('aiseo-api-key');

		if (toggleBtn && keyInput) {
			toggleBtn.addEventListener('click', () => {
				const type = keyInput.type === 'password' ? 'text' : 'password';
				keyInput.type = type;
			});
		}

		if (testBtn && keyInput) {
			testBtn.addEventListener('click', async () => {
				UI.loading(testBtn, true);
				try {
					const key = keyInput.value?.trim();
					const res = await API.testKey(key);
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
			openai_api_key:      document.getElementById('aiseo-api-key')?.value?.trim() || '',
			openai_model:        document.getElementById('aiseo-model')?.value            || '',
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
			data.append('nonce', nonce);

			try {
				const res = await fetch(Config.ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					body: data,
				});
				const json = await res.json();
				resultEl.textContent = json.data?.message || (json.success ? 'GitHub bağlantısı başarılı.' : 'GitHub sürümü okunamadı.');
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
		// Dashboard stats are server-rendered; no JS needed unless live refresh.
		// Future enhancement: poll for updated stats.
	}

	/* ------------------------------------------------------------------ */
	/* Router                                                               */
	/* ------------------------------------------------------------------ */
	function init() {
		const page = Config.currentPage || '';

		initModalClose();

		if (page === 'aiseo-posts' || page === '') {
			initPostListAnalyze();
			initPostDetailOptimize();
		}
		if (page === 'aiseo-bulk') {
			initBulkAnalysis();
		}
		if (page === 'aiseo-generator') {
			initArticleGenerator();
		}
		if (page === 'aiseo-links') {
			initInternalLinks();
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

		// Also init optimize buttons on any page (post detail is embedded in posts page)
		initPostDetailOptimize();
	}

	document.addEventListener('DOMContentLoaded', init);

	window.AISeo = { API, UI };
})();
