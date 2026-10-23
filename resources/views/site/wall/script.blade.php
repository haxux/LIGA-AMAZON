{{-- Lo que mueve «A Segunda» en el navegador: las reacciones sin recargar,
     el visor de fotos y el formulario (contador, vista previa y fotos
     reducidas antes de subir). Sin JavaScript todo sigue funcionando salvo
     las reacciones. --}}
<dialog id="wall-viewer" class="m-auto max-h-none max-w-none bg-transparent p-0 backdrop:bg-black/90">
    <button type="button" data-close class="fixed right-3 top-3 grid size-10 place-items-center rounded-full bg-white/10 text-2xl text-white" aria-label="Cerrar">×</button>
    <img alt="" class="max-h-[92vh] max-w-[96vw] object-contain">
</dialog>

<script>
(() => {
    const token = document.querySelector('meta[name="csrf-token"]')?.content
        ?? document.querySelector('[data-wall-composer] input[name="_token"]')?.value;
    const MAX = {{ App\Models\WallPost::MAX_IMAGES }};
    const EDGE = 1600;

    // «Me gusta» / «no me gusta»: el servidor devuelve cómo queda todo.
    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-wall-react]');
        if (!button || button.disabled) return;
        const bar = button.parentElement;
        bar.querySelectorAll('[data-wall-react]').forEach((b) => (b.disabled = true));
        try {
            const response = await fetch(button.dataset.wallReact, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify({ value: Number(button.dataset.value) }),
            });
            if (!response.ok) return;
            const state = await response.json();
            bar.querySelectorAll('[data-wall-react]').forEach((b) => {
                const value = Number(b.dataset.value);
                b.setAttribute('aria-pressed', String(state.mine === value));
                b.querySelector('[data-count]').textContent = value === 1 ? state.likes : state.dislikes;
            });
        } finally {
            bar.querySelectorAll('[data-wall-react]').forEach((b) => (b.disabled = false));
        }
    });

    // El visor.
    const viewer = document.getElementById('wall-viewer');
    document.addEventListener('click', (event) => {
        const photo = event.target.closest('[data-wall-photo]');
        if (photo) {
            viewer.querySelector('img').src = photo.dataset.wallPhoto;
            viewer.showModal();
        } else if (viewer.open && (event.target === viewer || event.target.closest('[data-close]'))) {
            viewer.close();
        }
    });

    const shrink = (file) => new Promise((resolve) => {
        const url = URL.createObjectURL(file);
        const image = new Image();
        image.onload = () => {
            const scale = Math.min(1, EDGE / Math.max(image.naturalWidth, image.naturalHeight));
            if (scale === 1 && file.size < 1.5e6) { URL.revokeObjectURL(url); return resolve(file); }
            const canvas = document.createElement('canvas');
            canvas.width = Math.round(image.naturalWidth * scale);
            canvas.height = Math.round(image.naturalHeight * scale);
            const context = canvas.getContext('2d');
            context.fillStyle = '#fff';
            context.fillRect(0, 0, canvas.width, canvas.height);
            context.drawImage(image, 0, 0, canvas.width, canvas.height);
            URL.revokeObjectURL(url);
            canvas.toBlob((blob) => resolve(blob
                ? new File([blob], file.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' })
                : file), 'image/jpeg', 0.85);
        };
        image.onerror = () => { URL.revokeObjectURL(url); resolve(file); };
        image.src = url;
    });

    document.querySelectorAll('[data-wall-composer]').forEach((form) => {
        const body = form.querySelector('textarea');
        const counter = form.querySelector('[data-counter]');
        const input = form.querySelector('[data-wall-files]');
        const previews = form.querySelector('[data-previews]');
        let files = [];

        const count = () => (counter.textContent = body.value.length + '/' + body.maxLength);
        body.addEventListener('input', count);
        count();

        const render = () => {
            const transfer = new DataTransfer();
            files.forEach((file) => transfer.items.add(file));
            input.files = transfer.files;
            previews.replaceChildren(...files.map((file, index) => {
                const cell = document.createElement('div');
                cell.className = 'relative aspect-square overflow-hidden rounded-[4px] bg-surface-muted';
                const img = document.createElement('img');
                img.src = URL.createObjectURL(file);
                img.className = 'size-full object-cover';
                img.alt = '';
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.textContent = '×';
                remove.setAttribute('aria-label', 'Quitar la foto');
                remove.className = 'absolute right-1 top-1 grid size-6 place-items-center rounded-full bg-black/70 text-sm text-white';
                remove.onclick = () => { files.splice(index, 1); render(); };
                cell.append(img, remove);
                return cell;
            }));
            previews.classList.toggle('hidden', files.length === 0);
            previews.classList.toggle('grid', files.length > 0);
        };

        input.addEventListener('change', async () => {
            const picked = [...input.files].filter((file) => !files.includes(file));
            const shrunk = await Promise.all(picked.map(shrink));
            files = [...files, ...shrunk].slice(0, MAX);
            render();
        });

        form.addEventListener('submit', (event) => {
            if (!body.value.trim() && files.length === 0) {
                event.preventDefault();
                body.focus();
                return;
            }
            form.querySelector('[type="submit"]').disabled = true;
        });
    });
})();
</script>
