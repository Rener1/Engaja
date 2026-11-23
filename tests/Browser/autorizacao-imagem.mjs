import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import puppeteer from 'puppeteer';

const [fixturePath, outputDir] = process.argv.slice(2);
assert(fixturePath && outputDir, 'Informe o HTML de teste e o diretório dos artefatos.');
const root = process.cwd();
const original = fs.readFileSync(fixturePath, 'utf8');
const confirmada = fs.readFileSync(path.join(outputDir, 'presenca-confirmada.html'), 'utf8');
const action = original.match(/action="([^"]+)" id="form-busca-presenca"/)[1];
const fixtureUrl = new URL('/autorizacao-imagem-qa', action).href;
const bootstrap = fs.readFileSync(path.join(root, 'node_modules/bootstrap/dist/js/bootstrap.bundle.min.js'), 'utf8');
const manifest = JSON.parse(fs.readFileSync(path.join(root, 'public/build/manifest.json'), 'utf8'));
const css = fs.readFileSync(path.join(root, 'public/build', manifest['resources/sass/app.scss'].file), 'utf8');
const preparar = html => html.replace(/<script\b[^>]*\bsrc=[^>]*>[\s\S]*?<\/script>/gi, '')
    .replace('</head>', () => '<style>' + css + '</style><script>' + bootstrap + '</script></head>');
const chrome = process.env.AUTORIZACAO_IMAGEM_CHROME || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const browser = await puppeteer.launch({ executablePath: chrome, headless: true, args: ['--no-sandbox'] });
const results = [];
try {
    for (const viewport of [{ width: 1440, height: 1000 }, { width: 390, height: 844 }]) {
        const page = await browser.newPage();
        await page.setViewport(viewport);
        const errors = [];
        const envios = [];
        let falharConsulta = false;
        page.on('pageerror', error => errors.push(error.message));
        await page.setRequestInterception(true);
        page.on('request', async request => {
            const url = new URL(request.url());
            if (request.method() === 'POST' && url.pathname.endsWith('/identificar')) {
                const { campo } = JSON.parse(request.postData());
                return request.respond({
                    status: falharConsulta ? 503 : 200, contentType: 'application/json',
                    body: JSON.stringify(falharConsulta
                        ? { message: 'Não foi possível verificar o cadastro.' }
                        : { autorizacao_pendente: campo !== 'respondido@example.invalid' })
                });
            }
            if (request.method() === 'POST' && request.url() === action) {
                envios.push(Object.fromEntries(new URLSearchParams(request.postData())));
                return request.respond({ status: 200, contentType: 'text/html; charset=utf-8', body: preparar(confirmada) });
            }
            if (request.url() === fixtureUrl) {
                return request.respond({ status: 200, contentType: 'text/html; charset=utf-8', body: preparar(original) });
            }
            const asset = path.resolve(root, 'public', url.pathname.replace(/^\/+/, ''));
            if (url.pathname.startsWith('/images/') && asset.startsWith(path.resolve(root, 'public') + path.sep) && fs.existsSync(asset)) {
                return request.respond({ status: 200, contentType: asset.endsWith('.svg') ? 'image/svg+xml' : 'image/png', body: fs.readFileSync(asset) });
            }
            return request.abort();
        });
        const abrir = () => page.goto(fixtureUrl, { waitUntil: 'domcontentloaded' });
        const preencher = valor => page.$eval('#campo', (input, valor) => {
            input.value = valor;
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }, valor);
        const aguardarPendente = () => page.waitForFunction(() =>
            !document.getElementById('autorizacao-presenca').hidden &&
            document.getElementById('status-identificacao-presenca').textContent === '');

        await abrir();
        assert.equal(await page.$eval('#confirmar-presenca', button => button.disabled), true);
        assert.equal(await page.$eval('#autorizacao-presenca', el => el.hidden), true);
        await preencher('pendente@example.invalid');
        await aguardarPendente();
        assert.equal(await page.$eval('#autorizacao-presenca', el => el.querySelectorAll(':checked').length), 0);
        assert.equal(await page.$$eval('#autorizacao-presenca input', els => els.every(input => input.required && !input.disabled)), true);
        assert.equal(await page.$eval('#confirmar-presenca', button => button.disabled), true);
        await page.$eval('#form-busca-presenca', form => form.requestSubmit());
        assert.equal(envios.length, 0, 'Presença não deve ser enviada sem escolha.');
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
        await page.screenshot({ path: path.join(outputDir, 'presenca-opcoes-' + viewport.width + '.png'), fullPage: true });

        await page.click('[data-bs-target="#termoAutorizacaoImagemModal"]');
        await page.waitForFunction(() => {
            const el = document.getElementById('termoAutorizacaoImagemModal');
            return el.classList.contains('show') && getComputedStyle(el).opacity === '1' && el.getAnimations({ subtree: true }).length === 0;
        });
        assert.equal(await page.$$eval('#termoAutorizacaoImagemModal input', els => els.length), 0, 'Modal do termo serve apenas para leitura.');
        await page.screenshot({ path: path.join(outputDir, 'termo-' + viewport.width + '.png') });
        await page.click('#termoAutorizacaoImagemModal .modal-footer button');
        await page.waitForFunction(() => document.getElementById('termoAutorizacaoImagemModal').style.display === 'none' && !document.querySelector('.modal-backdrop'));
        assert.equal(await page.$eval('#confirmar-presenca', button => button.disabled), true);

        await page.click('#autorizacaoPresencaNao');
        assert.equal(await page.$eval('#confirmar-presenca', button => button.disabled), false);
        await page.$eval('#confirmar-presenca', button => { button.click(); button.click(); });
        await page.waitForSelector('#confirmacaoPresencaModal.show');
        assert.equal(envios.length, 1);
        assert.equal(envios[0].autorizado, '0');
        assert.equal(await page.$('#autorizacaoImagemPresencaModal'), null);
        assert.equal(await page.$$eval('#confirmacaoPresencaModal a', els => els.length), 1, 'Avaliação deve continuar disponível.');

        await abrir();
        await preencher('respondido@example.invalid');
        await page.waitForFunction(() => !document.getElementById('confirmar-presenca').disabled);
        assert.equal(await page.$eval('#autorizacao-presenca', el => el.hidden), true);
        assert.equal(await page.$$eval('#autorizacao-presenca input', els => els.every(input => input.disabled && !input.required)), true);
        await preencher('pendente@example.invalid');
        assert.equal(await page.$eval('#confirmar-presenca', button => button.disabled), true);
        await aguardarPendente();
        assert.equal(await page.$eval('#autorizacao-presenca', el => el.querySelectorAll(':checked').length), 0);
        await page.click('#autorizacaoPresencaSim');
        assert.equal(await page.$eval('#confirmar-presenca', button => button.disabled), false);
        await preencher('outro@example.invalid');
        assert.equal(await page.$eval('#confirmar-presenca', button => button.disabled), true);
        await aguardarPendente();
        assert.equal(await page.$eval('#autorizacao-presenca', el => el.querySelectorAll(':checked').length), 0);

        falharConsulta = true;
        await preencher('falha@example.invalid');
        await page.waitForSelector('#tentar-identificacao-presenca:not([hidden])');
        assert.equal(await page.$eval('#confirmar-presenca', button => button.disabled), true);
        falharConsulta = false;
        await page.click('#tentar-identificacao-presenca');
        await aguardarPendente();
        await page.click('#autorizacaoPresencaSim');
        await page.click('#confirmar-presenca');
        await page.waitForSelector('#confirmacaoPresencaModal.show');
        assert.equal(envios.length, 2);
        assert.equal(envios[1].autorizado, '1');
        assert.deepEqual(errors, [], 'Erro JavaScript no formulário.');
        results.push({ viewport, scenarios: ['obrigatoriedade no formulário', 'termo apenas para leitura', 'recusa', 'aceite', 'resposta anterior', 'troca de identificador', 'falha e nova tentativa', 'avaliação preservada'] });
        await page.close();
    }
    console.log(JSON.stringify({ ok: true, results }));
} finally {
    await browser.close();
}
