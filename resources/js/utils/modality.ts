export type TModality = 'in_person' | 'remote';

interface ModalityOption {
    label: string;
    value: TModality;
}

// Single source of truth for the dropdown options in AppointmentDialog and
// TreatmentSessionDialog, and for the labels/icons reused wherever a
// modality needs to be displayed (calendar events, timeline badges).
export const modalityOptions: ModalityOption[] = [
    { label: 'Présentiel', value: 'in_person' },
    { label: 'À distance', value: 'remote' },
];

const modalityLabels: Record<TModality, string> = Object.fromEntries(
    modalityOptions.map((option) => [option.value, option.label]),
) as Record<TModality, string>;

export function modalityLabel(modality: string | null): string {
    return modalityLabels[modality as TModality] ?? modality ?? 'Présentiel';
}

const modalityIcons: Record<TModality, string> = {
    in_person: 'mdi-map-marker',
    remote: 'mdi-video',
};

export function modalityIcon(modality: string | null): string {
    return modalityIcons[modality as TModality] ?? modalityIcons.in_person;
}
