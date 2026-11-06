window.joyPosition = function (chat = {}) {
    const size = 56;
    const margin = 12;
    const key = 'tc-joy-position';
    return {
        ...chat,
        x: null, y: null, side: 'right', dragging: false,
        invitation: true, pointer: null, suppressClick: false, invitationTimer: null,
        get positionStyle() {
            return this.x === null ? '' : `left:${this.x}px;top:${this.y}px;right:auto;bottom:auto`;
        },
        init() {
            let saved;
            try { saved = JSON.parse(sessionStorage.getItem(key)); } catch (_) {}
            this.side = saved?.side === 'left' ? 'left' : 'right';
            const ratio = Number.isFinite(saved?.ratio) ? Math.max(0, Math.min(1, saved.ratio)) : 1;
            this.y = margin + ratio * Math.max(0, window.innerHeight - size - margin * 2);
            this.fit();
            this.invitationTimer = setTimeout(() => { this.invitation = false; }, 5000);
        },
        destroy() { clearTimeout(this.invitationTimer); },
        fit() {
            this.x = this.side === 'left' ? margin : Math.max(margin, window.innerWidth - size - margin);
            this.y = Math.max(margin, Math.min(this.y, window.innerHeight - size - margin));
        },
        savePosition() {
            const range = Math.max(1, window.innerHeight - size - margin * 2);
            try { sessionStorage.setItem(key, JSON.stringify({ side: this.side, ratio: (this.y - margin) / range })); } catch (_) {}
        },
        startDrag(event) {
            if (!event.isPrimary || event.button !== 0) return;
            this.pointer = { id: event.pointerId, x: event.clientX, y: event.clientY, left: this.x, top: this.y };
            this.suppressClick = false;
            event.currentTarget.setPointerCapture(event.pointerId);
        },
        moveDrag(event) {
            if (!this.pointer || event.pointerId !== this.pointer.id) return;
            const dx = event.clientX - this.pointer.x;
            const dy = event.clientY - this.pointer.y;
            if (!this.dragging && Math.hypot(dx, dy) < 6) return;
            this.dragging = true;
            this.invitation = false;
            this.suppressClick = true;
            this.x = Math.max(margin, Math.min(this.pointer.left + dx, window.innerWidth - size - margin));
            this.y = Math.max(margin, Math.min(this.pointer.top + dy, window.innerHeight - size - margin));
        },
        endDrag(event) {
            if (!this.pointer || event.pointerId !== this.pointer.id) return;
            if (this.dragging) {
                this.side = this.x + size / 2 < window.innerWidth / 2 ? 'left' : 'right';
                this.fit();
                this.savePosition();
            }
            this.pointer = null;
            this.dragging = false;
        },
        activate(event) {
            if (this.suppressClick && event.detail !== 0) { this.suppressClick = false; return; }
            this.suppressClick = false;
            this.invitation = false;
            this.toggle();
        },
        reposition(event) {
            if (!['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(event.key)) return;
            event.preventDefault();
            if (event.key === 'ArrowLeft') this.side = 'left';
            if (event.key === 'ArrowRight') this.side = 'right';
            if (event.key === 'ArrowUp') this.y -= 32;
            if (event.key === 'ArrowDown') this.y += 32;
            this.invitation = false;
            this.fit();
            this.savePosition();
        },
    };
};
