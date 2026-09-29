import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { createVuetify } from 'vuetify';
import AppWeekCalendar, { type AppCalendarEvent } from './AppWeekCalendar.vue';

const vuetify = createVuetify();
const columns = [{ id: 'c1', label: 'Lun', date: '2026-10-05' }];

function event(id: number, startsAt: string, endsAt: string): AppCalendarEvent {
    return { id, columnId: 'c1', startsAt, endsAt, title: `Event ${id}` };
}

function topOf(wrapper: ReturnType<typeof mount>, title: string): number {
    const el = wrapper.findAll('.app-week-calendar-event').find((e) => e.text().includes(title));
    return parseFloat((el!.element as HTMLElement).style.top);
}

describe('AppWeekCalendar', () => {
    it('greys out a blocked column, shows its badge and ignores slot clicks', async () => {
        const wrapper = mount(AppWeekCalendar, {
            props: {
                columns: [{ id: 'c1', label: 'Lun', date: '2026-10-05', blockedLabel: 'Congés' }, { id: 'c2', label: 'Mar', date: '2026-10-06' }],
                events: [],
            },
            global: { plugins: [vuetify] },
        });

        const [blocked, open] = wrapper.findAll('.app-week-calendar-column');
        expect(blocked.classes()).toContain('app-week-calendar-column--blocked');
        expect(wrapper.find('.app-week-calendar-header').text()).toContain('Congés');

        await blocked.find('.app-week-calendar-hour-slot').trigger('click');
        expect(wrapper.emitted('slot-click')).toBeUndefined();

        await open.find('.app-week-calendar-hour-slot').trigger('click');
        expect(wrapper.emitted('slot-click')).toHaveLength(1);
    });

    it('renders events outside the default range without crashing', () => {
        const wrapper = mount(AppWeekCalendar, {
            props: {
                columns,
                events: [event(1, '2026-10-05T06:00:00', '2026-10-05T06:30:00'), event(2, '2026-10-05T21:00:00', '2026-10-05T21:30:00')],
            },
            global: { plugins: [vuetify] },
        });

        expect(wrapper.text()).toContain('Event 1');
        expect(wrapper.text()).toContain('Event 2');
    });

    it('positions early and late events inside a widened grid', () => {
        const hourHeight = 56;
        const wrapper = mount(AppWeekCalendar, {
            props: {
                columns,
                startHour: 6,
                endHour: 22,
                hourHeight,
                events: [event(1, '2026-10-05T06:00:00', '2026-10-05T06:30:00'), event(2, '2026-10-05T21:00:00', '2026-10-05T21:30:00')],
            },
            global: { plugins: [vuetify] },
        });

        expect(topOf(wrapper, 'Event 1')).toBe(0);
        expect(topOf(wrapper, 'Event 2')).toBe(15 * hourHeight);
        expect(topOf(wrapper, 'Event 2')).toBeLessThan((22 - 6) * hourHeight);
    });
});
