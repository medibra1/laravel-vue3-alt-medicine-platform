import { router } from '@inertiajs/vue3';

/**
 * Mirrors @inertiajs/vue3's own <Link> click guard (see shouldIntercept in
 * @inertiajs/core) — a plain left click with no modifier keys and no other
 * target is intercepted into an Inertia visit; everything else (ctrl/cmd/
 * shift/alt-click, middle click, a link with target="_blank", a click
 * already prevented or landing in a contenteditable) is left alone so the
 * browser's native behavior (open in new tab, etc.) keeps working. Not
 * exported by @inertiajs/vue3 itself, so copied rather than imported.
 *
 * Typed as MouseEvent | KeyboardEvent because Vuetify's v-list-item also
 * dispatches a synthetic 'click' from its own onKeyDown handler (Enter/
 * Space) — that synthetic event only carries KeyboardEvent's shape, not
 * MouseEvent's (no `button`), hence the `'button' in event` guard below.
 */
function shouldIntercept(event: MouseEvent | KeyboardEvent): boolean {
    const target = event.target as HTMLElement | null;

    if ((target instanceof HTMLElement && target.isContentEditable) || event.defaultPrevented) {
        return false;
    }

    const currentTarget = event.currentTarget as HTMLElement | null;
    const isLink = currentTarget?.tagName.toLowerCase() === 'a';
    const linkTarget = isLink ? (currentTarget as HTMLAnchorElement).target : '';

    return !(
        (isLink && event.altKey) ||
        (isLink && event.ctrlKey) ||
        (isLink && event.metaKey) ||
        (isLink && event.shiftKey) ||
        (isLink && linkTarget !== '' && linkTarget !== '_self') ||
        (isLink && 'button' in event && event.button !== 0)
    );
}

/**
 * Vuetify's v-list-item/v-breadcrumbs-item render a real <a href> once
 * `href` is passed (ignoring any `tag` override), so keeping `href` gets
 * every native anchor behavior (open in new tab, copy link, hover preview)
 * for free — but the click still needs intercepting into an Inertia visit,
 * otherwise it's a full page reload. Pass this as `@click="(e) =>
 * handleNavClick(e, href)"` alongside the existing `href` prop, on any
 * Vuetify component that renders an anchor this way.
 */
export function handleNavClick(event: MouseEvent | KeyboardEvent, href: string): void {
    if (!shouldIntercept(event)) {
        return;
    }

    event.preventDefault();
    router.get(href);
}
