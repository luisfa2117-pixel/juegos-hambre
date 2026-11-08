<script>
    (() => {
        const root = document.documentElement;
        const controls = document.querySelector('[data-live-controls]');
        const toggle = controls.querySelector('[data-live-toggle]');
        const soundButton = controls.querySelector('[data-live-sound]');
        const skipButton = controls.querySelector('[data-live-skip]');
        const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;

        // localStorage puede fallar (navegación privada, cookies bloqueadas): entonces, valores por defecto
        const load = (key, fallback) => {
            try { return localStorage.getItem(key) ?? fallback; } catch (e) { return fallback; }
        };
        const save = (key, value) => {
            try { localStorage.setItem(key, value); } catch (e) {}
        };

        const prefs = {
            live: load('juegos.live', reduced ? 'off' : 'on') === 'on',
            sound: load('juegos.sound', 'on') === 'on',
        };

        const paint = () => {
            toggle.setAttribute('aria-pressed', prefs.live);
            toggle.textContent = prefs.live ? 'En directo: sí' : 'En directo: no';
            soundButton.setAttribute('aria-pressed', prefs.sound);
            soundButton.textContent = prefs.sound ? 'Cañonazos: sí' : 'Cañonazos: no';
        };
        controls.hidden = false;
        paint();

        // ---- El cañonazo, hecho con Web Audio: un golpe grave y un estruendo de ruido que se apaga ----

        let audio = null;
        const context = () => {
            audio ??= new (window.AudioContext || window.webkitAudioContext)();
            if (audio.state === 'suspended') audio.resume();
            return audio;
        };
        // Los navegadores no dejan sonar nada hasta que tocas la página: con el primer toque, se desbloquea
        addEventListener('pointerdown', () => prefs.sound && context(), { once: true });

        const cannon = () => {
            if (! prefs.sound) return;
            const ctx = context();
            if (ctx.state !== 'running') return;
            const now = ctx.currentTime;

            const thump = ctx.createOscillator();
            const thumpGain = ctx.createGain();
            thump.frequency.setValueAtTime(90, now);
            thump.frequency.exponentialRampToValueAtTime(30, now + 0.5);
            thumpGain.gain.setValueAtTime(0.9, now);
            thumpGain.gain.exponentialRampToValueAtTime(0.001, now + 0.6);
            thump.connect(thumpGain).connect(ctx.destination);
            thump.start(now);
            thump.stop(now + 0.6);

            const length = Math.floor(ctx.sampleRate * 1.8);
            const buffer = ctx.createBuffer(1, length, ctx.sampleRate);
            const data = buffer.getChannelData(0);
            for (let i = 0; i < length; i++) {
                data[i] = (Math.random() * 2 - 1) * Math.pow(1 - i / length, 3);
            }
            const noise = ctx.createBufferSource();
            const filter = ctx.createBiquadFilter();
            const noiseGain = ctx.createGain();
            noise.buffer = buffer;
            filter.type = 'lowpass';
            filter.frequency.setValueAtTime(1200, now);
            filter.frequency.exponentialRampToValueAtTime(100, now + 1.5);
            noiseGain.gain.setValueAtTime(0.7, now);
            noiseGain.gain.exponentialRampToValueAtTime(0.001, now + 1.8);
            noise.connect(filter).connect(noiseGain).connect(ctx.destination);
            noise.start(now);
        };

        // ---- La ronda en directo ----

        const round = document.querySelector('[data-live-round]');
        let finish = () => {};

        toggle.addEventListener('click', () => {
            prefs.live = ! prefs.live;
            save('juegos.live', prefs.live ? 'on' : 'off');
            paint();
            if (! prefs.live) finish();
        });
        soundButton.addEventListener('click', () => {
            prefs.sound = ! prefs.sound;
            save('juegos.sound', prefs.sound ? 'on' : 'off');
            paint();
            if (prefs.sound) context();
        });

        if (! round || ! root.classList.contains('live-pending')) {
            root.classList.remove('live-pending');
            return;
        }

        const items = [...round.querySelectorAll('[data-live-item]')];
        const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));
        let skipped = false;

        // Cuánto se queda cada cosa en pantalla antes de la siguiente: lo justo para leerla
        const pause = item => {
            const kind = item.dataset.liveKind;
            if (kind === 'arena') return 3200;
            if (kind === 'sky') return 1200 + item.querySelectorAll('[data-live-face]').length * 600;
            const reading = Math.min(3000, 900 + item.textContent.trim().length * 16);
            return reading + Number(item.dataset.liveDeaths || 0) * 700;
        };

        const show = item => {
            item.classList.add('live-shown');
            if (item.dataset.liveKind === 'sky') item.classList.add('live-sky');
            if (Number(item.dataset.liveDeaths) > 0) item.classList.add('live-death');
        };

        finish = () => {
            if (skipped) return;
            skipped = true;
            items.forEach(item => item.classList.add('live-shown'));
            root.classList.remove('live-pending');
            skipButton.hidden = true;
        };
        skipButton.hidden = false;
        skipButton.addEventListener('click', finish);

        (async () => {
            await sleep(600);

            for (const item of items) {
                if (skipped) return;
                show(item);
                item.scrollIntoView({ block: 'nearest', behavior: reduced ? 'auto' : 'smooth' });

                const deaths = Number(item.dataset.liveDeaths || 0);
                for (let i = 0; i < deaths; i++) {
                    setTimeout(cannon, 350 + i * 700);
                }

                await sleep(pause(item));
            }

            finish();
        })();
    })();
</script>
