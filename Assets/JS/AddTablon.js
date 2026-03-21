/**
 * AddTablon PWA - Client-side logic for the "Añadir Tablón" PWA page.
 * Handles PWA install prompt, photo preview, price calculation,
 * AJAX form submission, IndexedDB offline queue, automatic sync
 * when back online, and deferred login via modal.
 */
(function () {
    'use strict';

    // ── DOM references ──────────────────────────────────────────────────
    var form = document.getElementById('tablonForm');
    var imageInput = document.getElementById('imageInput');
    var photoArea = document.getElementById('photoArea');
    var photoPreview = document.getElementById('photoPreview');
    var tipoMadera = document.getElementById('tipo_madera');
    var tipoTablon = document.getElementById('tipo_tablon');
    var largoInput = document.getElementById('largo');
    var anchoInput = document.getElementById('ancho');
    var espesorInput = document.getElementById('espesor');
    var priceAmount = document.getElementById('priceAmount');
    var priceDetail = document.getElementById('priceDetail');
    var btnPublish = document.getElementById('btnPublish');
    var alertBox = document.getElementById('alertBox');
    var offlineBanner = document.getElementById('offlineBanner');
    var pendingBadge = document.getElementById('pendingBadge');
    var btnSync = document.getElementById('btnSync');
    var installBanner = document.getElementById('installBanner');
    var btnInstall = document.getElementById('btnInstall');
    var btnDismissInstall = document.getElementById('btnDismissInstall');

    // Login modal DOM references
    var loginModal = document.getElementById('loginModal');
    var loginForm = document.getElementById('loginForm');
    var loginModalError = document.getElementById('loginModalError');
    var btnLogin = document.getElementById('btnLogin');

    // Price table is injected by the Twig template as a global variable
    var prices = window.priceTable || [];

    // Current photo as base64 dataURL (kept in memory for offline queue)
    var currentPhotoDataURL = '';

    // Pending entry waiting for login before submission
    var pendingLoginEntry = null;

    // ── PWA install prompt ──────────────────────────────────────────────
    var deferredInstallPrompt = null;
    var installTip = document.getElementById('installTip');
    var installTipText = document.getElementById('installTipText');

    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        deferredInstallPrompt = e;
        if (installBanner) {
            installBanner.style.display = 'flex';
        }
        // Hide manual tip if the native prompt is available
        if (installTip) {
            installTip.style.display = 'none';
        }
    });

    if (btnInstall) {
        btnInstall.addEventListener('click', function () {
            if (!deferredInstallPrompt) return;
            deferredInstallPrompt.prompt();
            deferredInstallPrompt.userChoice.then(function () {
                deferredInstallPrompt = null;
                if (installBanner) {
                    installBanner.style.display = 'none';
                }
            });
        });
    }

    if (btnDismissInstall) {
        btnDismissInstall.addEventListener('click', function () {
            if (installBanner) {
                installBanner.style.display = 'none';
            }
            deferredInstallPrompt = null;
        });
    }

    window.addEventListener('appinstalled', function () {
        deferredInstallPrompt = null;
        if (installBanner) {
            installBanner.style.display = 'none';
        }
        if (installTip) {
            installTip.style.display = 'none';
        }
    });

    // Show manual install instructions if beforeinstallprompt does not fire
    if (installTip && installTipText) {
        setTimeout(function () {
            if (deferredInstallPrompt) return; // Native prompt available, no need
            if (window.matchMedia('(display-mode: standalone)').matches) return; // Already installed
            if (navigator.standalone) return; // iOS standalone mode

            var isIOS = /iP(hone|ad|od)/i.test(navigator.userAgent);
            if (isIOS) {
                installTipText.innerHTML = '<b>Instalar:</b> Pulsa <i class="fa-solid fa-arrow-up-from-bracket"></i> Compartir y luego <b>Añadir a pantalla de inicio</b>';
            } else {
                installTipText.innerHTML = '<b>Instalar:</b> Abre el menú del navegador <i class="fa-solid fa-ellipsis-vertical"></i> y selecciona <b>Añadir a pantalla de inicio</b>';
            }
            installTip.style.display = 'flex';
        }, 4000);
    }

    // ── IndexedDB helpers ───────────────────────────────────────────────
    var DB_NAME = 'tablonPWA';
    var DB_VERSION = 1;
    var STORE_NAME = 'pendingSlabs';

    function openDB(callback) {
        var request = indexedDB.open(DB_NAME, DB_VERSION);
        request.onupgradeneeded = function (e) {
            var db = e.target.result;
            if (!db.objectStoreNames.contains(STORE_NAME)) {
                db.createObjectStore(STORE_NAME, { keyPath: 'id', autoIncrement: true });
            }
        };
        request.onsuccess = function (e) { callback(null, e.target.result); };
        request.onerror = function (e) { callback(e.target.error, null); };
    }

    function savePending(entry, callback) {
        openDB(function (err, db) {
            if (err) { callback(err); return; }
            var tx = db.transaction(STORE_NAME, 'readwrite');
            tx.objectStore(STORE_NAME).add(entry);
            tx.oncomplete = function () { callback(null); };
            tx.onerror = function (e) { callback(e.target.error); };
        });
    }

    function getAllPending(callback) {
        openDB(function (err, db) {
            if (err) { callback(err, []); return; }
            var tx = db.transaction(STORE_NAME, 'readonly');
            var req = tx.objectStore(STORE_NAME).getAll();
            req.onsuccess = function () { callback(null, req.result || []); };
            req.onerror = function (e) { callback(e.target.error, []); };
        });
    }

    function deletePending(id, callback) {
        openDB(function (err, db) {
            if (err) { callback(err); return; }
            var tx = db.transaction(STORE_NAME, 'readwrite');
            tx.objectStore(STORE_NAME).delete(id);
            tx.oncomplete = function () { callback(null); };
            tx.onerror = function (e) { callback(e.target.error); };
        });
    }

    // ── Photo preview handler ───────────────────────────────────────────
    imageInput.addEventListener('change', function () {
        var file = this.files[0];
        if (!file) return;

        var reader = new FileReader();
        reader.onload = function (e) {
            currentPhotoDataURL = e.target.result;
            photoPreview.src = currentPhotoDataURL;
            photoArea.classList.add('has-photo');
        };
        reader.readAsDataURL(file);
        recalculate();
    });

    // ── Price calculation ───────────────────────────────────────────────
    [tipoMadera, tipoTablon, largoInput, anchoInput, espesorInput].forEach(function (el) {
        el.addEventListener('change', recalculate);
        el.addEventListener('input', recalculate);
    });

    function recalculate() {
        var wood = tipoMadera.value;
        var slab = tipoTablon.value;
        var largo = parseFloat(largoInput.value) || 0;
        var ancho = parseFloat(anchoInput.value) || 0;
        var espesor = parseFloat(espesorInput.value) || 0;

        if (!wood || !slab || largo <= 0 || ancho <= 0 || espesor <= 0) {
            priceAmount.textContent = '— €';
            priceDetail.textContent = '';
            btnPublish.disabled = true;
            return;
        }

        var precioM2 = lookupPrice(wood, slab, espesor);
        if (precioM2 === null) {
            priceAmount.textContent = '— €';
            priceDetail.textContent = window.TABLON_I18N.noPriceDefined;
            btnPublish.disabled = true;
            return;
        }

        var areaM2 = (largo / 100) * (ancho / 100);
        var total = precioM2 * areaM2;

        priceAmount.textContent = total.toFixed(2) + ' €';
        priceDetail.textContent = precioM2.toFixed(2) + ' €/m² × ' + areaM2.toFixed(4) + ' m²';
        btnPublish.disabled = false;
    }

    function lookupPrice(wood, slab, espesor) {
        // Exact match
        for (var i = 0; i < prices.length; i++) {
            if (prices[i].tipo_madera === wood &&
                prices[i].tipo_tablon === slab &&
                prices[i].espesor === espesor) {
                return prices[i].precio_m2;
            }
        }

        // Closest thickness match for the wood/slab combination
        var closest = null;
        var minDiff = Infinity;
        for (var j = 0; j < prices.length; j++) {
            if (prices[j].tipo_madera === wood && prices[j].tipo_tablon === slab) {
                var diff = Math.abs(prices[j].espesor - espesor);
                if (diff < minDiff) {
                    minDiff = diff;
                    closest = prices[j];
                }
            }
        }
        return closest ? closest.precio_m2 : null;
    }

    // ── Form submission ─────────────────────────────────────────────────
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        hideAlert();

        var entry = collectFormData();
        if (!entry) return;

        btnPublish.disabled = true;
        btnPublish.classList.add('loading');

        if (!navigator.onLine) {
            // Offline → save to queue
            saveToQueue(entry);
            return;
        }

        // Online → try to submit
        submitEntry(entry, function (ok, message, resultCode) {
            btnPublish.classList.remove('loading');
            if (ok) {
                showAlert('success', message);
                resetForm();
            } else if (resultCode === 'login-required') {
                // Not authenticated → show login modal, then retry
                pendingLoginEntry = entry;
                showLoginModal();
                btnPublish.disabled = false;
            } else {
                // Network error during submit → save to queue
                if (message === '__network_error__') {
                    saveToQueue(entry);
                } else {
                    showAlert('error', message);
                    btnPublish.disabled = false;
                }
            }
        });
    });

    function collectFormData() {
        return {
            tipo_madera: tipoMadera.value,
            tipo_tablon: tipoTablon.value,
            largo: largoInput.value,
            ancho: anchoInput.value,
            espesor: espesorInput.value,
            imageDataURL: currentPhotoDataURL || '',
            timestamp: new Date().toISOString()
        };
    }

    function submitEntry(entry, callback) {
        var formData = new FormData();
        formData.append('action', 'add-tablon');
        formData.append('tipo_madera', entry.tipo_madera);
        formData.append('tipo_tablon', entry.tipo_tablon);
        formData.append('largo', entry.largo);
        formData.append('ancho', entry.ancho);
        formData.append('espesor', entry.espesor);

        // Convert base64 dataURL back to a Blob for file upload
        if (entry.imageDataURL) {
            var blob = dataURLtoBlob(entry.imageDataURL);
            if (blob) {
                formData.append('image', blob, 'tablon-photo.jpg');
            }
        }

        var xhr = new XMLHttpRequest();
        xhr.open('POST', window.location.pathname, true);
        xhr.timeout = 30000;
        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) return;
            if (xhr.status === 200) {
                try {
                    var resp = JSON.parse(xhr.responseText);
                    callback(resp.result === 'ok', resp.message || 'OK', resp.result);
                } catch (ex) {
                    callback(false, window.TABLON_I18N.serverError, 'error');
                }
            } else {
                callback(false, '__network_error__', 'error');
            }
        };
        xhr.ontimeout = function () { callback(false, '__network_error__', 'error'); };
        xhr.onerror = function () { callback(false, '__network_error__', 'error'); };
        xhr.send(formData);
    }

    function saveToQueue(entry) {
        savePending(entry, function (err) {
            btnPublish.classList.remove('loading');
            if (err) {
                showAlert('error', window.TABLON_I18N.serverError);
                btnPublish.disabled = false;
            } else {
                showAlert('success', window.TABLON_I18N.savedOffline);
                resetForm();
                refreshPendingCount();
            }
        });
    }

    // ── Login modal ─────────────────────────────────────────────────────
    function showLoginModal() {
        if (!loginModal) return;
        loginModalError.style.display = 'none';
        loginModalError.textContent = '';
        loginModal.classList.add('active');
        var nickInput = document.getElementById('modalNick');
        if (nickInput) nickInput.focus();
    }

    function hideLoginModal() {
        if (!loginModal) return;
        loginModal.classList.remove('active');
        pendingLoginEntry = null;
    }

    // Close modal when clicking the overlay background
    if (loginModal) {
        loginModal.addEventListener('click', function (e) {
            if (e.target === loginModal) {
                hideLoginModal();
            }
        });
    }

    if (loginForm) {
        loginForm.addEventListener('submit', function (e) {
            e.preventDefault();
            loginModalError.style.display = 'none';

            var nick = document.getElementById('modalNick').value.trim();
            var password = document.getElementById('modalPassword').value;

            if (!nick || !password) return;

            btnLogin.disabled = true;
            btnLogin.classList.add('loading');

            var formData = new FormData();
            formData.append('action', 'login');
            formData.append('fsNick', nick);
            formData.append('fsPassword', password);

            function resetLoginBtn() {
                btnLogin.disabled = false;
                btnLogin.classList.remove('loading');
            }

            function showLoginError(msg) {
                resetLoginBtn();
                loginModalError.textContent = msg;
                loginModalError.style.display = 'block';
            }

            var xhr = new XMLHttpRequest();
            xhr.open('POST', window.location.pathname, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.timeout = 15000;
            xhr.onreadystatechange = function () {
                if (xhr.readyState !== 4) return;

                if (xhr.status === 200) {
                    try {
                        var resp = JSON.parse(xhr.responseText);
                        if (resp.result === 'ok') {
                            resetLoginBtn();
                            hideLoginModal();
                            loginForm.reset();
                            if (pendingLoginEntry) {
                                var entry = pendingLoginEntry;
                                pendingLoginEntry = null;
                                btnPublish.disabled = true;
                                btnPublish.classList.add('loading');
                                submitEntry(entry, function (ok, message) {
                                    btnPublish.classList.remove('loading');
                                    if (ok) {
                                        showAlert('success', message);
                                        resetForm();
                                    } else {
                                        showAlert('error', message);
                                        btnPublish.disabled = false;
                                    }
                                });
                            }
                        } else {
                            showLoginError(resp.message || 'Error');
                        }
                    } catch (ex) {
                        showLoginError(window.TABLON_I18N.serverError);
                    }
                } else {
                    showLoginError(window.TABLON_I18N.serverError);
                }
            };
            xhr.ontimeout = function () { showLoginError(window.TABLON_I18N.serverError); };
            xhr.onerror = function () { showLoginError(window.TABLON_I18N.serverError); };
            xhr.send(formData);
        });
    }

    // ── Sync pending items ──────────────────────────────────────────────
    function syncPending() {
        if (!navigator.onLine) return;

        getAllPending(function (err, items) {
            if (err || items.length === 0) return;

            showAlert('success', window.TABLON_I18N.syncing.replace('{n}', items.length));
            if (btnSync) { btnSync.disabled = true; }

            var idx = 0;
            var ok = 0;
            var fail = 0;

            function next() {
                if (idx >= items.length) {
                    finishSync(ok, fail);
                    return;
                }
                var item = items[idx];
                idx++;
                submitEntry(item, function (success) {
                    if (success) {
                        ok++;
                        deletePending(item.id, function () { next(); });
                    } else {
                        fail++;
                        next();
                    }
                });
            }
            next();
        });
    }

    function finishSync(ok, fail) {
        if (btnSync) { btnSync.disabled = false; }
        refreshPendingCount();
        if (fail === 0 && ok > 0) {
            showAlert('success', window.TABLON_I18N.syncDone.replace('{n}', ok));
        } else if (fail > 0) {
            showAlert('error', window.TABLON_I18N.syncPartial.replace('{ok}', ok).replace('{fail}', fail));
        }
    }

    // ── Pending count badge ─────────────────────────────────────────────
    function refreshPendingCount() {
        getAllPending(function (err, items) {
            var count = (err || !items) ? 0 : items.length;
            if (pendingBadge) {
                pendingBadge.textContent = count;
                pendingBadge.style.display = count > 0 ? 'inline-flex' : 'none';
            }
            if (btnSync) {
                btnSync.style.display = count > 0 ? 'inline-flex' : 'none';
            }
        });
    }

    // ── Online / offline detection ──────────────────────────────────────
    function updateOnlineStatus() {
        if (offlineBanner) {
            offlineBanner.style.display = navigator.onLine ? 'none' : 'flex';
        }
    }

    window.addEventListener('online', function () {
        updateOnlineStatus();
        syncPending();
    });
    window.addEventListener('offline', updateOnlineStatus);

    if (btnSync) {
        btnSync.addEventListener('click', function () {
            syncPending();
        });
    }

    // ── Helpers ─────────────────────────────────────────────────────────
    function dataURLtoBlob(dataURL) {
        try {
            var parts = dataURL.split(',');
            var mime = parts[0].match(/:(.*?);/)[1];
            var b64 = atob(parts[1]);
            var arr = new Uint8Array(b64.length);
            for (var i = 0; i < b64.length; i++) {
                arr[i] = b64.charCodeAt(i);
            }
            return new Blob([arr], { type: mime });
        } catch (e) {
            return null;
        }
    }

    function resetForm() {
        form.reset();
        photoArea.classList.remove('has-photo');
        photoPreview.src = '';
        currentPhotoDataURL = '';
        priceAmount.textContent = '— €';
        priceDetail.textContent = '';
        btnPublish.disabled = true;
    }

    function showAlert(type, message) {
        alertBox.className = 'alert alert-' + type;
        alertBox.textContent = message;
        alertBox.style.display = 'block';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function hideAlert() {
        alertBox.style.display = 'none';
        alertBox.className = 'alert';
    }

    // ── Init ────────────────────────────────────────────────────────────
    updateOnlineStatus();
    refreshPendingCount();
})();
