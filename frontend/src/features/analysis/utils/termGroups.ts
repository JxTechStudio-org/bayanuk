// The key-term cards of the result, in order (MVP 5, step 4: compensation, working hours, leave, contract;
// the Result Dashboard card adds benefits and restrictions). The keys are from the integration contract, section 13.
// The contract card also holds the MVP 6 groups that have no card of their own: the document (employer, job title),
// probation, notice and termination, because they are all terms of the contract.
const TERM_GROUPS = [
  {
    id: 'compensation',
    keys: [
      'compensation.basic_salary',
      'compensation.housing',
      'compensation.transportation',
      'compensation.other',
      'compensation.total_monthly',
      'compensation.pay_frequency',
    ],
  },
  { id: 'workingTime', keys: ['working_time.hours_per_week', 'working_time.working_days', 'working_time.overtime'] },
  { id: 'leave', keys: ['annual_leave_days', 'other_leave'] },
  {
    id: 'contract',
    keys: [
      'employer_name',
      'job_title',
      'contract.type',
      'contract.start_date',
      'contract.duration_months',
      'contract.renewal',
      'probation_days',
      'probation_extension_allowed',
      'notice.employee_days',
      'notice.employer_days',
      'termination.conditions',
      'termination.penalties',
      'termination.training_repayment',
    ],
  },
  {
    id: 'benefits',
    keys: [
      'benefits.medical_insurance',
      'benefits.tickets',
      'benefits.bonus',
      'benefits.training',
      'benefits.end_of_service',
    ],
  },
  {
    id: 'restrictions',
    keys: ['non_compete.exists', 'non_compete.duration_months', 'non_compete.scope', 'confidentiality', 'exclusivity'],
  },
] as const

type TermGroup = (typeof TERM_GROUPS)[number]
const group = (id: TermGroup['id']) => TERM_GROUPS.find((item) => item.id === id) as TermGroup

// Where the cards sit on a wide screen: two columns, one cell after the other, each cell a stack of cards.
// The three short cards share the first cell, beside the long contract card, so the two columns end at about
// the same height. Benefits and restrictions are about the same length and make the next row.
// On a phone the cells are one column. In both, the order stays the order above.
export const TERM_LAYOUT: TermGroup[][] = [
  [group('compensation'), group('workingTime'), group('leave')],
  [group('contract')],
  [group('benefits')],
  [group('restrictions')],
]
