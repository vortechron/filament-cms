{{--
    A form's email to the shop (Mail\FormSubmissionMail). Every value is a
    visitor's: escaped, and new lines kept with nl2br after escaping.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<body style="margin: 0; padding: 24px; font-family: Arial, Helvetica, sans-serif; font-size: 15px; color: #1f2937;">
    <p style="margin: 0 0 16px;">{{ __('filament-cms::forms.mail_intro', ['form' => $formName]) }}</p>
    <table cellpadding="8" cellspacing="0" style="border-collapse: collapse; width: 100%; max-width: 640px;">
        @foreach ($rows as $row)
            <tr>
                <th align="left" valign="top" style="width: 35%; border-bottom: 1px solid #e5e7eb; font-weight: bold;">{{ $row['label'] }}</th>
                <td valign="top" style="border-bottom: 1px solid #e5e7eb;">{!! nl2br(e($row['value'])) !!}</td>
            </tr>
        @endforeach
    </table>
    @if ($pagePath !== null)
        <p style="margin: 16px 0 0; font-size: 13px; color: #6b7280;">{{ __('filament-cms::forms.mail_page', ['page' => $pagePath]) }}</p>
    @endif
</body>
</html>
