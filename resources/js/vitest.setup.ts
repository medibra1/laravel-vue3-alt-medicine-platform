// jsdom has no ResizeObserver; Vuetify components built on VSlideGroup
// (VTabs among them) require one just to mount, regardless of what the
// test actually exercises.
class ResizeObserverStub {
    observe() {}
    unobserve() {}
    disconnect() {}
}

globalThis.ResizeObserver ??= ResizeObserverStub as unknown as typeof ResizeObserver;
