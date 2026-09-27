<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="margin:0; padding:0; background-color:#f3f4f6; font-family: Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f3f4f6; padding:30px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" style="max-width:480px; background-color:#ffffff; border-radius:8px; overflow:hidden;" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="background-color:#047857; padding:20px 30px;">
                            <span style="color:#ffffff; font-size:20px; font-weight:bold;">AMANAH</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:30px;">
                            <h2 style="margin:0 0 16px 0; color:#111827; font-size:18px;">
                                {{ $estNouveauCompte ? 'Bienvenue sur AMANAH' : 'Réinitialisation de votre mot de passe' }}
                            </h2>

                            <p style="color:#374151; font-size:14px; line-height:1.6;">Assalamou aleykoum {{ $nomDestinataire }},</p>

                            <p style="color:#374151; font-size:14px; line-height:1.6;">
                                @if ($estNouveauCompte)
                                    Un accès vous a été créé sur la plateforme AMANAH (AND DOX S.A.).
                                @else
                                    Votre mot de passe a été réinitialisé à votre demande.
                                @endif
                            </p>

                            <table role="presentation" style="width:100%; background-color:#f9fafb; border-radius:6px; margin:20px 0;" cellpadding="12" cellspacing="0">
                                <tr>
                                    <td style="font-size:13px; color:#6b7280;">Identifiant</td>
                                    <td style="font-size:14px; color:#111827; font-weight:bold;">{{ $email }}</td>
                                </tr>
                                <tr>
                                    <td style="font-size:13px; color:#6b7280;">Mot de passe temporaire</td>
                                    <td style="font-size:14px; color:#111827; font-weight:bold; font-family: monospace;">{{ $motDePasse }}</td>
                                </tr>
                            </table>

                            <p style="color:#374151; font-size:14px; line-height:1.6;">
                                Pour votre sécurité, vous devrez choisir un nouveau mot de passe dès votre première connexion.
                            </p>

                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:20px 0;">
                                <tr>
                                    <td style="background-color:#047857; border-radius:6px;">
                                        <a href="{{ url('/login') }}" style="display:inline-block; padding:12px 24px; color:#ffffff; text-decoration:none; font-size:14px; font-weight:bold;">
                                            Se connecter
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="color:#9ca3af; font-size:12px; line-height:1.6; margin-top:24px;">
                                Si vous n'êtes pas à l'origine de cette demande, contactez immédiatement votre gestionnaire.
                            </p>

                            <p style="color:#374151; font-size:14px; margin-top:20px;">
                                Barak'ALLAH Fikoum,<br>AND DOX S.A.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
