import type { Term } from '@/features/analysis/types'

// The result the fake API returns for every ready document: an Arabic employment contract with the
// example values of the MVP Definition (section 6). Some terms are not found, so the "not found" state shows too.
const found = (key: string, value: string | number, page: number, quote: string, unit?: string): Term => ({
  key,
  found: true,
  value,
  ...(unit ? { unit } : {}),
  page,
  quote,
})
const notFound = (key: string): Term => ({ key, found: false })

export const FAKE_TERMS: Term[] = [
  found('employer_name', 'شركة الأفق للتقنية', 1, 'الطرف الأول: شركة الأفق للتقنية'),
  found('job_title', 'محاسب', 1, 'يعمل الطرف الثاني بوظيفة محاسب'),
  found('contract.type', 'fixed_term', 1, 'مدة هذا العقد سنة واحدة'),
  found('contract.start_date', '2026-11-01', 1, 'يبدأ العمل بهذا العقد في 1 نوفمبر 2026'),
  found('contract.duration_months', 12, 1, 'مدة هذا العقد سنة واحدة', 'months'),
  found('contract.renewal', 'يتجدد تلقائيًا لمدة مماثلة', 1, 'يتجدد العقد تلقائيًا لمدة مماثلة ما لم يخطر أحد الطرفين الآخر'),
  found('probation_days', 90, 2, 'يخضع الطرف الثاني لفترة تجربة مدتها تسعون يومًا', 'days'),
  found('probation_extension_allowed', 'yes', 2, 'يجوز تمديد فترة التجربة باتفاق الطرفين'),
  found('notice.employee_days', 30, 7, 'على الطرف الثاني إشعار الطرف الأول قبل ثلاثين يومًا', 'days'),
  notFound('notice.employer_days'),
  found('compensation.basic_salary', 8000, 3, 'الراتب الأساسي ثمانية آلاف ريال', 'SAR'),
  found('compensation.housing', 1500, 3, 'بدل سكن ألف وخمسمائة ريال', 'SAR'),
  found('compensation.transportation', 500, 3, 'بدل نقل خمسمائة ريال', 'SAR'),
  notFound('compensation.other'),
  found('compensation.total_monthly', 10000, 3, 'إجمالي الأجر الشهري عشرة آلاف ريال', 'SAR'),
  found('compensation.pay_frequency', 'monthly', 3, 'يصرف الأجر في نهاية كل شهر ميلادي'),
  found('working_time.hours_per_week', 48, 4, 'ساعات العمل ثمان وأربعون ساعة أسبوعيًا', 'hours_per_week'),
  found('working_time.working_days', 'من الأحد إلى الخميس', 4, 'أيام العمل من الأحد إلى الخميس'),
  found('working_time.overtime', 'يُحتسب وفق نظام العمل', 4, 'يُحتسب العمل الإضافي وفق نظام العمل'),
  found('annual_leave_days', 21, 5, 'يستحق الطرف الثاني إجازة سنوية مدتها واحد وعشرون يومًا', 'days'),
  notFound('other_leave'),
  found('benefits.medical_insurance', 'yes', 5, 'يوفر الطرف الأول تأمينًا طبيًا للموظف وعائلته'),
  found('benefits.tickets', 'yes', 5, 'تذكرة سفر سنوية للموظف'),
  notFound('benefits.bonus'),
  notFound('benefits.training'),
  found('benefits.end_of_service', 'yes', 8, 'يستحق الطرف الثاني مكافأة نهاية الخدمة وفق نظام العمل'),
  found('non_compete.exists', 'yes', 9, 'لا يجوز للطرف الثاني العمل لدى منافس'),
  found('non_compete.duration_months', 12, 9, 'لمدة اثني عشر شهرًا من انتهاء العقد', 'months'),
  found('non_compete.scope', 'نفس القطاع داخل المملكة', 9, 'في نفس القطاع داخل المملكة'),
  found('confidentiality', 'yes', 9, 'يلتزم الطرف الثاني بالمحافظة على سرية المعلومات'),
  notFound('exclusivity'),
  found('termination.conditions', 'وفق المادة 80 من نظام العمل', 8, 'يحق للطرف الأول فسخ العقد وفق المادة 80 من نظام العمل'),
  notFound('termination.penalties'),
  found('termination.training_repayment', 'يرد تكلفة التدريب إذا ترك العمل خلال سنة', 8, 'يلتزم الطرف الثاني برد تكلفة التدريب إذا ترك العمل خلال سنة من انتهائه'),
]

export const FAKE_SUMMARY =
  'هذا عقد عمل محدد المدة لوظيفة محاسب لمدة سنة، يبدأ في 1 نوفمبر 2026 ويتجدد تلقائيًا. الراتب الشهري الإجمالي 10,000 ريال، وفترة التجربة 90 يومًا قابلة للتمديد. فيه شرط عدم منافسة لمدة 12 شهرًا بعد انتهاء العقد، وشرط رد تكلفة التدريب إذا تركت العمل خلال سنة.'
