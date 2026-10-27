// The key-term cards of the result, in order (MVP 5, step 4: compensation, working hours, leave, contract;
// the Result Dashboard card adds benefits and restrictions). The keys are from the integration contract, section 13.
// The contract card also holds the MVP 6 groups that have no card of their own: the document (employer, job title),
// probation, notice and termination, because they are all terms of the contract.
export const TERM_GROUPS = [
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
