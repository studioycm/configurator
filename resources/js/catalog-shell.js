export function catalogShell(browserWindow = window) {
    const storageKey = 'aquestia:catalog:sidebar-collapsed:v1';
    const cleanup = [];
    let opener;

    return {
        desktopCollapsed: false,
        mobileOpen: false,
        isDesktop: false,

        get collapsed() {
            return this.isDesktop && this.desktopCollapsed;
        },

        init() {
            try {
                this.desktopCollapsed = browserWindow.localStorage.getItem(storageKey) === 'true';
            } catch {
                this.desktopCollapsed = false;
            }
            const media = browserWindow.matchMedia('(min-width: 1024px)');
            this.isDesktop = media.matches;
            const changeViewport = () => {
                this.isDesktop = media.matches;
                this.closeMobile(false);
            };
            media.addEventListener('change', changeViewport);
            cleanup.push(() => media.removeEventListener('change', changeViewport));
            const navigating = () => this.closeMobile(false);
            browserWindow.document.addEventListener('livewire:navigating', navigating);
            cleanup.push(() => browserWindow.document.removeEventListener('livewire:navigating', navigating));
        },

        destroy() {
            cleanup.splice(0).forEach(remove => remove());
        },

        toggleDesktop() {
            if (!this.isDesktop) return;
            this.desktopCollapsed = !this.desktopCollapsed;
            try {
                browserWindow.localStorage.setItem(storageKey, String(this.desktopCollapsed));
            } catch {
                // Navigation remains usable when browser storage is unavailable.
            }
        },

        openMobile() {
            if (this.isDesktop) return;
            opener = browserWindow.document.activeElement;
            this.mobileOpen = true;
            this.$nextTick(() => this.$refs.closeNavigation?.focus());
        },

        closeMobile(returnFocus = true) {
            const wasOpen = this.mobileOpen;
            this.mobileOpen = false;
            if (wasOpen && returnFocus && !this.isDesktop) {
                this.$nextTick(() => {
                    if (opener?.isConnected) opener.focus();
                });
            }
        },

        hasOpenDialog() {
            return Boolean(browserWindow.document.querySelector('dialog[open]'));
        },

        trapFocus(event) {
            if (event.key !== 'Tab' || this.isDesktop || !this.mobileOpen || this.hasOpenDialog()) return;
            const controls = [...this.$refs.navigation.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), [tabindex="0"]')]
                .filter(element => element.getClientRects().length && !element.closest('[inert]'));
            const first = controls[0];
            const last = controls.at(-1);
            const active = browserWindow.document.activeElement;
            if (!first) {
                event.preventDefault();
                return;
            }
            if (event.shiftKey && active === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && active === last) {
                event.preventDefault();
                first.focus();
            }
        },
    };
}
