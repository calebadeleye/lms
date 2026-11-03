{{--
    Branded free-assessment email. Table layout with inline styles on purpose:
    Gmail, Outlook and phone mail apps ignore most modern CSS. Images are
    absolute URLs on the public site (frontend public/branding and public/email).
--}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="x-apple-disable-message-reformatting">
<title>Your FREE Personality Assessment is ready!</title>
<style>
  @media only screen and (max-width: 620px) {
    .container { width: 100% !important; }
    .stack { display: block !important; width: auto !important; }
    .hero-text { padding: 32px 24px 24px !important; }
    .hero-title { font-size: 26px !important; }
    .hero-img { width: 100% !important; height: auto !important; }
    .pad { padding-left: 24px !important; padding-right: 24px !important; }
    .btn a { display: block !important; }
  }
</style>
</head>
<body style="margin:0; padding:0; background-color:#f3f1ea; -webkit-text-size-adjust:100%;">
{{-- Inbox preview text --}}
<div style="display:none; max-height:0; overflow:hidden; mso-hide:all;">Hello {{ $firstName }}, your FREE Personality Assessment is ready. Take it now!</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f3f1ea;">
<tr>
<td align="center" style="padding:24px 12px;">

<table role="presentation" class="container" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px; max-width:600px; background-color:#ffffff; border-radius:18px; overflow:hidden; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;">

  {{-- Logo between two gold rules --}}
  <tr>
    <td style="padding:26px 24px 22px;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
          <td width="30%" valign="middle"><div style="height:3px; background-color:#f4b728; border-radius:2px; font-size:0; line-height:0;">&nbsp;</div></td>
          <td align="center" valign="middle" style="padding:0 16px;">
            <a href="{{ $siteUrl }}" style="text-decoration:none;">
              <img src="{{ $logoUrl }}" width="148" height="53" alt="HR GEMs Coach Network" style="display:block; border:0; width:148px; height:53px;">
            </a>
          </td>
          <td width="30%" valign="middle"><div style="height:3px; background-color:#f4b728; border-radius:2px; font-size:0; line-height:0;">&nbsp;</div></td>
        </tr>
      </table>
    </td>
  </tr>

  {{-- Teal hero: headline + photo --}}
  <tr>
    <td style="background-color:#073d40;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
          <td class="stack hero-text" width="58%" valign="middle" style="padding:36px 20px 36px 36px;">
            <div class="hero-title" style="font-family:Georgia,'Times New Roman',serif; font-size:36px; line-height:1.15; font-weight:bold; color:#ffffff;">
              Your <span style="background-color:#f4b728; color:#073d40; padding:0 8px; border-radius:6px;">FREE</span><br>
              Personality Assessment Is Ready!
            </div>
            <div style="width:64px; height:4px; background-color:#f4b728; border-radius:2px; margin-top:18px; font-size:0; line-height:0;">&nbsp;</div>
          </td>
          <td class="stack" width="42%" valign="bottom" style="padding:0; border-left:6px solid #f4b728;">
            <img class="hero-img" src="{{ $heroUrl }}" width="240" height="267" alt="An HR GEMs member sharing her thoughts at our 2026 event" style="display:block; border:0; width:240px; height:267px;">
          </td>
        </tr>
      </table>
    </td>
  </tr>

  {{-- Greeting --}}
  <tr>
    <td class="pad" style="padding:32px 40px 8px; font-size:17px; line-height:1.6; color:#172326;">
      <p style="margin:0 0 10px;">Hello {{ $firstName }},</p>
      <p style="margin:0;">Your <span style="background-color:#f4b728; color:#073d40; font-weight:bold; padding:1px 6px; border-radius:4px;">FREE</span> Personality Assessment is ready!</p>
    </td>
  </tr>

  {{-- Primary button --}}
  <tr>
    <td class="pad btn" align="center" style="padding:22px 40px 28px;">
      <table role="presentation" cellpadding="0" cellspacing="0" border="0">
        <tr>
          <td align="center" style="background-color:#073d40; border-radius:40px;">
            <a href="{{ $assessmentLink }}" style="display:inline-block; padding:18px 34px; font-size:17px; font-weight:bold; letter-spacing:0.5px; color:#ffffff; text-decoration:none; border-radius:40px;">
              📋&nbsp; TAKE YOUR FREE ASSESSMENT NOW &nbsp;→
            </a>
          </td>
        </tr>
      </table>
    </td>
  </tr>

  {{-- "Next step" box --}}
  <tr>
    <td class="pad" style="padding:0 32px;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#fdf8ea; border-radius:14px;">
        <tr>
          <td width="64" valign="middle" align="center" style="padding:22px 0 22px 22px; font-size:40px; line-height:1;">📈</td>
          <td valign="middle" style="padding:22px 24px 22px 18px; border-left:2px solid #f4b728;">
            <p style="margin:0 0 4px; font-size:17px; font-weight:bold; color:#172326;">But don't stop at discovering your personality!</p>
            <p style="margin:0 0 8px; font-size:15px; color:#3f4c4e;">Hurry and take the next step.</p>
            <p style="margin:0; font-size:16px; font-weight:800; letter-spacing:0.3px; line-height:1.4; color:#006c70;">LEARN HOW TO GET A FULFILLING JOB AND MAKE MONEY WITH YOUR STRENGTHS.</p>
          </td>
        </tr>
      </table>
    </td>
  </tr>

  {{-- Course button --}}
  <tr>
    <td class="pad btn" align="center" style="padding:28px 40px 30px;">
      <table role="presentation" cellpadding="0" cellspacing="0" border="0">
        <tr>
          <td align="center" style="background-color:#f4b728; border-radius:40px;">
            <a href="{{ $courseLink }}" style="display:inline-block; padding:18px 34px; font-size:17px; font-weight:bold; letter-spacing:0.5px; color:#073d40; text-decoration:none; border-radius:40px;">
              🎓&nbsp; ENROL IN OUR COURSE TODAY &nbsp;→
            </a>
          </td>
        </tr>
      </table>
    </td>
  </tr>

  {{-- Sign-off --}}
  <tr>
    <td class="pad" style="padding:0 40px 30px; color:#172326;">
      <p style="margin:0 0 4px; font-size:15px;">Warm regards,</p>
      <p style="margin:0; font-size:19px; font-weight:bold; color:#073d40;">HR GEMs Coach Network</p>
      <p style="margin:2px 0 0; font-size:12px; letter-spacing:2px; color:#5b6a6c;">Great. Excellent. Minds.</p>
    </td>
  </tr>

  {{-- Gold and teal footer bands --}}
  <tr><td style="height:8px; background-color:#f4b728; font-size:0; line-height:0;">&nbsp;</td></tr>
  <tr>
    <td align="center" style="background-color:#073d40; padding:16px 24px; font-size:12px; line-height:1.6; color:#cfe2e0;">
      You're receiving this because you asked for the free assessment at
      <a href="{{ $siteUrl }}" style="color:#f4b728; text-decoration:none;">hrgemscoachnetwork.com</a>.
    </td>
  </tr>

</table>

</td>
</tr>
</table>
</body>
</html>
