export function configuratorProductSearch(code = '') {
    return {
        query: code,
        selectedCode: code,
        options: [],
        open: false,
        loading: false,
        choosing: false,
        failed: false,
        activeIndex: -1,
        request: 0,

        async search() {
            const request = ++this.request;
            this.open = true;
            this.loading = true;
            this.failed = false;
            this.options = [];
            this.activeIndex = -1;

            try {
                const results = await this.$wire.searchProducts(this.query);
                if (request !== this.request || !this.open) return;
                this.options = results;
            } catch {
                if (request === this.request) this.failed = true;
            } finally {
                if (request === this.request) this.loading = false;
            }
        },

        close() {
            ++this.request;
            this.open = false;
            this.loading = false;
            this.activeIndex = -1;
            this.query = this.selectedCode;
        },

        move(direction) {
            if (!this.open) {
                this.search();
                return;
            }
            if (!this.options.length) return;
            this.activeIndex = (this.activeIndex + direction + this.options.length) % this.options.length;
            this.$nextTick(() => this.$refs.results.querySelector('[aria-selected="true"]')?.scrollIntoView({ block: 'nearest' }));
        },

        chooseActive() {
            if (this.activeIndex < 0 || this.loading) return;
            return this.choose(this.options[this.activeIndex]);
        },

        async choose(option = null) {
            if (this.choosing) return;
            this.close();
            this.failed = false;
            this.choosing = true;
            try {
                const acceptedId = await this.$wire.chooseProduct(option?.id ?? null);
                if (acceptedId !== (option ? Number(option.id) : null)) {
                    this.failed = true;
                    return;
                }
                this.selectedCode = option?.label ?? '';
                this.query = this.selectedCode;
            } catch {
                this.failed = true;
            } finally {
                this.choosing = false;
            }
        },
    };
}
