import type { ChatAnswer } from '@/features/chat/types'

// The answers of the fake chat, about the fake contract (fakeResult.ts). A few topics are known;
// any other question gets "not found in your document". The reply follows the question's language (MVP 8).
const TOPICS: { words: RegExp; ar: string; en: string; page: number; quote: string }[] = [
  {
    words: /إشعار|notice/i,
    ar: 'حسب العقد، عليك إشعار صاحب العمل قبل 30 يومًا إذا أردت إنهاء العقد. ولم يذكر العقد فترة إشعار صاحب العمل.',
    en: 'The contract says you give the employer 30 days of notice to end the contract. It does not state the employer’s notice period.',
    page: 7,
    quote: 'على الطرف الثاني إشعار الطرف الأول قبل ثلاثين يومًا',
  },
  {
    words: /تجربة|probation/i,
    ar: 'فترة التجربة 90 يومًا ويمكن تمديدها باتفاق الطرفين. ولا يذكر العقد شروط الاستقالة خلالها، فاسأل جهة العمل أو مختصًا للتأكد.',
    en: 'The probation is 90 days and can be extended if both sides agree. The contract does not say how to resign during it, so ask the employer or a specialist.',
    page: 2,
    quote: 'يخضع الطرف الثاني لفترة تجربة مدتها تسعون يومًا',
  },
  {
    words: /راتب|أجر|salary|pay/i,
    ar: 'الراتب الأساسي 8,000 ريال، مع بدل سكن 1,500 ريال وبدل نقل 500 ريال، والإجمالي 10,000 ريال شهريًا.',
    en: 'The basic salary is SAR 8,000, with SAR 1,500 for housing and SAR 500 for transportation: SAR 10,000 a month in total.',
    page: 3,
    quote: 'الراتب الأساسي ثمانية آلاف ريال',
  },
]

export function fakeAnswer(question: string, remaining: number): ChatAnswer {
  const arabic = /[؀-ۿ]/.test(question)
  const topic = TOPICS.find(({ words }) => words.test(question))
  if (!topic) {
    return {
      answer: arabic ? 'لم أجد إجابة لهذا السؤال في مستندك.' : 'I could not find the answer to this question in your document.',
      citations: [],
      answer_found: false,
      remaining_questions: remaining,
    }
  }
  return {
    answer: arabic ? topic.ar : topic.en,
    citations: [{ page: topic.page, quote: topic.quote }],
    answer_found: true,
    remaining_questions: remaining,
  }
}
