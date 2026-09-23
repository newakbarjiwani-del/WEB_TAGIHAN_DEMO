<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ICT QR Dummy</title>
    <script src="https://cdn.jsdelivr.net/npm/qrious@4.0.2/dist/qrious.min.js"></script>
    <style>
        :root {
            --bg: #f4f7f4;
            --card: #ffffff;
            --text: #1f2a24;
            --muted: #5b6b63;
            --line: #dce6df;
            --accent: #1b7a4e;
            --accent-dark: #14603d;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: var(--bg);
            color: var(--text);
        }
        .wrap {
            max-width: 980px;
            margin: 0 auto;
            padding: 24px 16px 40px;
        }
        h1 {
            margin: 0 0 6px;
            font-size: 22px;
        }
        .sub {
            margin: 0 0 20px;
            color: var(--muted);
            font-size: 14px;
        }
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 18px;
        }
        label {
            display: block;
            font-size: 13px;
            margin: 0 0 6px;
            color: var(--muted);
        }
        input, textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--line);
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 12px;
        }
        textarea { min-height: 70px; resize: vertical; }
        button {
            background: var(--accent);
            color: #fff;
            border: 0;
            border-radius: 8px;
            padding: 10px 16px;
            font-size: 14px;
            cursor: pointer;
        }
        button:hover { background: var(--accent-dark); }
        .btn-row { display: flex; gap: 8px; flex-wrap: wrap; }
        button.secondary {
            background: #fff;
            color: var(--accent-dark);
            border: 1px solid var(--accent);
        }
        button.secondary:hover { background: #f3faf6; }
        button.small {
            padding: 6px 10px;
            font-size: 12px;
        }
        pre.json-box {
            margin: 0;
            max-height: 360px;
            overflow: auto;
            background: #111827;
            color: #e5e7eb;
            border-radius: 8px;
            padding: 12px;
            font-size: 12px;
            line-height: 1.45;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .qr-box {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 320px;
            text-align: center;
        }
        canvas { background: #fff; padding: 8px; border-radius: 8px; }
        .meta {
            width: 100%;
            margin-top: 14px;
            font-size: 13px;
            text-align: left;
        }
        .meta div {
            margin-bottom: 6px;
            word-break: break-all;
        }
        .status {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 12px;
            background: #fff4d6;
            color: #8a6d00;
        }
        .status.paid {
            background: #d9f5e5;
            color: #14603d;
        }
        .alert {
            display: none;
            margin: 0 0 16px;
            padding: 10px 12px;
            border-radius: 8px;
            background: #fde8e8;
            color: #9b1c1c;
            font-size: 13px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        th, td {
            text-align: left;
            padding: 8px 6px;
            border-bottom: 1px solid var(--line);
        }
        tr { cursor: pointer; }
        tr:hover td { background: #f3faf6; }
        .history { margin-top: 16px; }
        @media (max-width: 800px) {
            .grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <h1>ICT QR Dummy</h1>
        <p class="sub">Generate QR dummy, tampilkan dengan QRious, dan simpan ke tabel <code>qr_dummy</code>.</p>
        <div id="alert" class="alert"></div>
        <div class="grid">
            <div class="card">
                <form id="generateForm">
                    <label for="amount">Nominal</label>
                    <input id="amount" name="amount" type="number" min="1" step="1" required placeholder="Contoh: 10000">

                    <label for="description">Keterangan (opsional)</label>
                    <textarea id="description" name="description" placeholder="Tes dummy QR"></textarea>

                    <div class="btn-row">
                        <button id="submitBtn" type="submit">Generate QR</button>
                    </div>
                </form>
            </div>
            <div class="card qr-box">
                <canvas id="qrCanvas" width="260" height="260"></canvas>
                <div id="qrMeta" class="meta">QR belum digenerate.</div>
                <div class="btn-row" style="margin-top:12px;">
                    <button id="checkStatusBtn" class="secondary" type="button" disabled>Cek Status</button>
                </div>
            </div>
        </div>
        <div class="card history">
            <h2 style="margin:0 0 12px;font-size:16px;">Balikan server 43</h2>
            <pre id="serverResponseBox" class="json-box">Belum ada response.</pre>
        </div>
        <div class="card history">
            <h2 style="margin:0 0 12px;font-size:16px;">Riwayat qr_dummy</h2>
            <div id="historyBox">Memuat data...</div>
        </div>
    </div>
    <script>
        const form = document.getElementById('generateForm');
        const alertBox = document.getElementById('alert');
        const submitBtn = document.getElementById('submitBtn');
        const checkStatusBtn = document.getElementById('checkStatusBtn');
        const qrMeta = document.getElementById('qrMeta');
        const historyBox = document.getElementById('historyBox');
        const serverResponseBox = document.getElementById('serverResponseBox');
        let currentItem = null;
        const qr = new QRious({
            element: document.getElementById('qrCanvas'),
            size: 260,
            value: 'ICT QR Dummy'
        });

        function showAlert(message) {
            alertBox.style.display = 'block';
            alertBox.textContent = message;
        }

        function hideAlert() {
            alertBox.style.display = 'none';
            alertBox.textContent = '';
        }

        function formatRupiah(value) {
            const number = Number(value || 0);
            return 'Rp ' + number.toLocaleString('id-ID');
        }

        function showServerResponse(data) {
            serverResponseBox.textContent = JSON.stringify(data, null, 2);
        }

        async function parseJsonResponse(response) {
            const text = await response.text();
            try {
                return { ok: response.ok, data: JSON.parse(text) };
            } catch (error) {
                throw new Error(text || 'Response bukan JSON');
            }
        }

        function renderQr(item) {
            const content = item.rawQrData || item.qris_content || '';
            if (!content) {
                showAlert('QR content kosong.');
                return;
            }
            currentItem = item;
            checkStatusBtn.disabled = !(item.id || item.qris_id || item.transactionQrId);
            qr.value = content;
            qrMeta.innerHTML = `
                <div><strong>Nominal:</strong> ${formatRupiah(item.amount)}</div>
                <div><strong>VA Number:</strong> ${item.vano || '-'}</div>
                <div><strong>QRIS ID:</strong> ${item.transactionQrId || item.qris_id || '-'}</div>
                <div><strong>Status:</strong> <span class="status ${item.status === 'paid' ? 'paid' : ''}">${item.status || 'pending'}</span></div>
            `;
            if (item.serverResponse) {
                showServerResponse(item.serverResponse);
            }
        }

        async function checkStatus(payload) {
            hideAlert();
            checkStatusBtn.disabled = true;
            checkStatusBtn.textContent = 'Cek status...';
            try {
                const response = await fetch('checkStatus.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const parsed = await parseJsonResponse(response);
                const result = parsed.data;
                if (result.serverResponse) {
                    showServerResponse(result.serverResponse);
                } else {
                    showServerResponse(result);
                }
                if (!parsed.ok || result.success === false || result.error) {
                    throw new Error(result.message || result.error || 'Gagal cek status');
                }
                renderQr(result);
                await loadHistory();
            } catch (error) {
                showAlert(error.message);
            } finally {
                checkStatusBtn.disabled = !currentItem;
                checkStatusBtn.textContent = 'Cek Status';
            }
        }

        async function loadHistory() {
            try {
                const response = await fetch('list.php');
                const parsed = await parseJsonResponse(response);
                const result = parsed.data;
                if (!result.success) {
                    historyBox.textContent = result.error || 'Gagal memuat riwayat.';
                    return;
                }
                if (!result.data.length) {
                    historyBox.textContent = 'Belum ada data di qr_dummy.';
                    return;
                }
                historyBox.innerHTML = `
                    <table>
                        <thead>
                            <tr>
                                <th>Waktu</th>
                                <th>Nominal</th>
                                <th>VA Number</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            ${result.data.map((row) => `
                                <tr data-id="${row.id}">
                                    <td>${row.created_at}</td>
                                    <td>${formatRupiah(row.amount)}</td>
                                    <td>${row.vano}</td>
                                    <td><span class="status ${row.status === 'paid' ? 'paid' : ''}">${row.status}</span></td>
                                    <td><button class="secondary small" type="button" data-check-id="${row.id}">Cek Status</button></td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                `;
                historyBox.querySelectorAll('tr[data-id]').forEach((rowEl, index) => {
                    rowEl.addEventListener('click', () => {
                        const row = result.data[index];
                        renderQr(row);
                        if (row.response_payload) {
                            try {
                                showServerResponse(JSON.parse(row.response_payload));
                            } catch (error) {
                                showServerResponse(row.response_payload);
                            }
                        }
                    });
                });
                historyBox.querySelectorAll('button[data-check-id]').forEach((btn) => {
                    btn.addEventListener('click', (event) => {
                        event.stopPropagation();
                        checkStatus({ id: Number(btn.getAttribute('data-check-id')) });
                    });
                });
            } catch (error) {
                historyBox.textContent = 'Gagal memuat riwayat: ' + error.message;
            }
        }

        checkStatusBtn.addEventListener('click', () => {
            if (!currentItem) {
                return;
            }
            checkStatus({
                id: currentItem.id || null,
                qris_id: currentItem.qris_id || currentItem.transactionQrId || ''
            });
        });

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            hideAlert();
            submitBtn.disabled = true;
            submitBtn.textContent = 'Generating...';

            const payload = {
                amount: document.getElementById('amount').value,
                description: document.getElementById('description').value.trim()
            };

            try {
                const response = await fetch('generateQr.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const parsed = await parseJsonResponse(response);
                const result = parsed.data;
                if (result.serverResponse) {
                    showServerResponse(result.serverResponse);
                } else {
                    showServerResponse(result);
                }
                if (!parsed.ok || result.success === false || result.error) {
                    throw new Error(result.message || result.error || 'Gagal generate QR');
                }
                renderQr(result);
                await loadHistory();
            } catch (error) {
                showAlert(error.message);
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Generate QR';
            }
        });

        loadHistory();
    </script>
</body>
</html>
