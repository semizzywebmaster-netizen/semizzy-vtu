<?php
return ['strategy'=>env('MAILER_SMTP_STRATEGY','failover'),'cooldown_seconds'=>(int)env('MAILER_SMTP_COOLDOWN_SECONDS',300)];