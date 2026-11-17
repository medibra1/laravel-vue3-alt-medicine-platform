import { createAppI18n } from '@/lib/i18n';
import { config } from '@vue/test-utils';

// Components call useI18n()/$t — every mount gets the French instance.
const i18n = createAppI18n('fr');
config.global.plugins = [...(config.global.plugins ?? []), i18n];

// jsdom has no ResizeObserver; Vuetify components built on VSlideGroup
// (VTabs among them) require one just to mount, regardless of what the
// test actually exercises.
class ResizeObserverStub {
    observe() {}
    unobserve() {}
    disconnect() {}
}

globalThis.ResizeObserver ??= ResizeObserverStub as unknown as typeof ResizeObserver;

// jsdom has no visualViewport either; Vuetify's VOverlay (which VDialog is
// built on) reads it to position itself, even for a dialog that's never
// actually rendered visibly in a test. A minimal stub with just the fields
// VOverlay's locationStrategies touches is enough to mount.
globalThis.visualViewport ??= {
    width: 1024,
    height: 768,
    addEventListener: () => {},
    removeEventListener: () => {},
} as unknown as VisualViewport;
