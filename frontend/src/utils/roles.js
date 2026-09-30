export function dashboardPathFor(role) {
  switch (role) {
    case 'admin':
      return '/admin'
    case 'dentist':
      return '/dentist'
    default:
      return '/patient'
  }
}