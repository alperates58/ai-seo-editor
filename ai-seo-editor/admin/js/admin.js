/* AI SEO Editor — Admin JavaScript */
/* globals AISeoConfig */

(function () {
	'use strict';

	const Config  = window.AISeoConfig || {};
	const i18n    = Config.i18n || {};
	const restUrl = Config.restUrl || '';
	const nonce   = Config.nonce || '';
	const githubNonce = Config.githubNonce || nonce;

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
			const text = await res.text();
			let json = {};
			try {
				json = text ? JSON.parse(text) : {};
			} catch (e) {
				json = { message: text.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim() };
			}
			if (!res.ok) throw json;
			return json;
		},
		analyzePost:      (pid, force) => API.request('/analyze/' + pid, 'POST', { force: !!force }),
		getAnalysis:      (pid)        => API.request('/analyze/' + pid),
		optimize:         (pid, op)    => API.request('/optimize', 'POST', { post_id: pid, operation: op }),
		fullOptimize:     (pid)        => API.request('/optimize/full', 'POST', { post_id: pid }),
		regeneratePost:   (pid)        => API.request('/regenerate/' + pid, 'POST'),
		applyOptimize:    (data)       => API.request('/optimize/apply', 'POST', data),
		bulkAnalyze:      (ids)        => API.request('/bulk-analyze', 'POST', { post_ids: ids }),
		generateArticle:  (params)     => API.request('/generate', 'POST', params),
		createDraft:      (data)       => API.request('/generate/create-draft', 'POST', data),
		getLinks:         (pid)        => API.request('/links/' + pid),
		computeLinks:     (pid)        => API.request('/links/' + pid + '/compute', 'POST'),
		applyLinks:       (pid, ids, content) => {
			const body = { post_id: pid, suggestion_ids: ids };
			if (typeof content === 'string') body.content = content;
			return API.request('/links/apply', 'POST', body);
		},
		optimizeTags:     (pid, data)  => API.request('/tags/optimize/' + pid, 'POST', data || {}),
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
					if (seoScore) seoScore.textContent = data.seo_score || '—';
					if (readScore) readScore.textContent = data.readability_score || '—';
					UI.notice('aiseo-editor-notice', 'Analiz yenilendi.', 'success');
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
					const res = await API.fullOptimize(postId);
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
				if (data.title) applyEditorTitle(data.title);
				if (data.content) applyEditorContent(data.content);
				if (data.meta) applyEditorMeta(data.meta, data.post_id || Config.postId);
				if (data.tags) applyEditorTags(data.tags);
				UI.notice('aiseo-editor-notice', 'Tam düzeltme editöre aktarıldı. Kontrol edip Güncelle ile kaydedin.', 'success');
			}
		});
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
					const res = await API.fullOptimize(postId);
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
			if (data.title) applyEditorTitle(data.title);
			if (data.content) applyEditorContent(data.content);
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
		content = cleanGeneratedHtml(content);
		if (window.wp?.data?.dispatch) {
			const editor = window.wp.data.dispatch('core/editor');
			if (editor?.editPost) {
				editor.editPost({ content });
				return;
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
