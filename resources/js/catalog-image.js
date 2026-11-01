export function catalogImage(initialImage = null) {
    const identity = image => image ? JSON.stringify([image.id, image.url, image.thumbnail_url, image.width, image.height]) : null;

    return {
        image: initialImage,
        imageIdentity: identity(initialImage),
        attemptIdentity: 0,
        currentUrl: initialImage?.thumbnail_url ?? initialImage?.url ?? null,
        status: initialImage ? 'loading' : 'missing',
        triedOriginal: false,

        get attempts() {
            return this.currentUrl && this.attemptIdentity !== 0
                ? [{ id: this.attemptIdentity, url: this.currentUrl, width: this.image.width, height: this.image.height }]
                : [];
        },

        init() {
            this.$nextTick(() => {
                const image = this.$refs?.initialImage;
                if (this.attemptIdentity !== 0 || !this.currentUrl || !image?.complete) return;
                if (image.naturalWidth > 0) this.onLoad(0);
                else this.onError(0);
            });
        },

        setImage(image) {
            const next = identity(image);
            if (next === this.imageIdentity) return;
            this.image = image;
            this.imageIdentity = next;
            this.attemptIdentity++;
            this.currentUrl = image?.thumbnail_url ?? image?.url ?? null;
            this.triedOriginal = false;
            this.status = image ? 'loading' : 'missing';
        },

        onError(attemptIdentity) {
            if (attemptIdentity !== this.attemptIdentity || !this.currentUrl) return;
            if (!this.triedOriginal && this.image.url !== this.currentUrl) {
                this.triedOriginal = true;
                this.attemptIdentity++;
                this.currentUrl = this.image.url;
                this.status = 'loading';
                return;
            }
            this.currentUrl = null;
            this.status = 'unavailable';
        },

        onLoad(attemptIdentity) {
            if (attemptIdentity === this.attemptIdentity && this.currentUrl) this.status = 'ready';
        },
    };
}
