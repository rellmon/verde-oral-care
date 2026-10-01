// Wording of the confirmation dialog for each status change that cannot be undone.
export const CHANGE_DIALOGS = {
  completed: {
    title: 'Mark as completed?',
    confirmLabel: 'Yes, mark completed',
    note: 'This cannot be undone.',
  },
  no_show: {
    title: 'Mark as no-show?',
    confirmLabel: 'Yes, mark no-show',
    note: 'This cannot be undone.',
  },
  cancelled: {
    title: 'Cancel this appointment?',
    confirmLabel: 'Yes, cancel it',
    note: 'The time slot becomes available to other patients again. This cannot be undone.',
  },
}