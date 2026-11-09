<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('messages.magic_link_subject') ?? 'تسجيل الدخول إلى بيانك' }}</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f9fafb; margin: 0; padding: 24px; color: #111827;">
    <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 560px; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
        <tr>
            <td style="background-color: #1b1b18; padding: 24px; text-align: center;">
                <h1 style="color: #ffffff; margin: 0; font-size: 22px;">بيانك | Bayanuk</h1>
            </td>
        </tr>
        <tr>
            <td style="padding: 32px 28px;">
                <h2 style="font-size: 18px; margin-top: 0; color: #1f2937;">
                    {{ app()->getLocale() === 'ar' ? 'تسجيل الدخول إلى حسابك' : 'Sign in to your account' }}
                </h2>
                <p style="font-size: 15px; color: #4b5563; line-height: 1.6;">
                    {{ app()->getLocale() === 'ar' 
                        ? 'انقر على الزر أدناه لإتمام تسجيل الدخول إلى منصة بيانك. هذا الرابط صالح للاستخدام مرة واحدة ولمدة 15 دقيقة فقط:' 
                        : 'Click the button below to sign in to Bayanuk. This magic link is single-use and expires in 15 minutes:' }}
                </p>
                <div style="text-align: center; margin: 32px 0;">
                    <a href="{{ $url }}" style="background-color: #0284c7; color: #ffffff; padding: 12px 28px; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 15px; display: inline-block;">
                        {{ app()->getLocale() === 'ar' ? 'تسجيل الدخول الآن' : 'Sign In Now' }}
                    </a>
                </div>
                <p style="font-size: 13px; color: #9ca3af; line-height: 1.5;">
                    {{ app()->getLocale() === 'ar' 
                        ? 'إذا لم تقم بطلب هذا الرابط، يمكنك تجاهل هذا البريد الإلكتروني بأمان.' 
                        : 'If you did not request this link, you can safely ignore this email.' }}
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
