/**
 * AddTablon PWA - Client-side logic for the "Añadir Tablón" PWA page.
 * Handles photo preview, price calculation from the embedded price table,
 * and form submission via AJAX.
 */
(function () {
    'use strict';

    // DOM references
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

    // Price table is injected by the Twig template as a global variable
    var prices = window.priceTable || [];

    // Photo preview handler
    imageInput.addEventListener('change', function () {
        var file = this.files[0];
        if (!file) return;

        var reader = new FileReader();
        reader.onload = function (e) {
            photoPreview.src = e.target.result;
            photoArea.classList.add('has-photo');
        };
        reader.readAsDataURL(file);
        recalculate();
    });

    // Recalculate price when any input changes
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
            priceDetail.textContent = 'No hay precio definido para esta combinación';
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

    // Form submission via AJAX
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        hideAlert();

        btnPublish.disabled = true;
        btnPublish.classList.add('loading');

        var formData = new FormData(form);
        var url = window.location.pathname;

        var xhr = new XMLHttpRequest();
        xhr.open('POST', url, true);
        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) return;

            btnPublish.classList.remove('loading');

            if (xhr.status === 200) {
                try {
                    var resp = JSON.parse(xhr.responseText);
                    if (resp.result === 'ok') {
                        showAlert('success', resp.message || 'Tablón añadido correctamente');
                        form.reset();
                        photoArea.classList.remove('has-photo');
                        photoPreview.src = '';
                        priceAmount.textContent = '— €';
                        priceDetail.textContent = '';
                        btnPublish.disabled = true;
                    } else {
                        showAlert('error', resp.message || 'Error al añadir el tablón');
                        btnPublish.disabled = false;
                    }
                } catch (ex) {
                    showAlert('error', 'Error de comunicación con el servidor');
                    btnPublish.disabled = false;
                }
            } else {
                showAlert('error', 'Error de red (' + xhr.status + ')');
                btnPublish.disabled = false;
            }
        };
        xhr.send(formData);
    });

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
})();
