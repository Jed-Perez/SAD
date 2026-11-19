<?php
declare(strict_types=1);
require_once __DIR__.'/scheduling.php';
require_once __DIR__.'/notifications.php';
require_once __DIR__.'/plans.php';
require_once __DIR__.'/quotations.php';
require_once __DIR__.'/jobs.php';
require_once __DIR__.'/forecast.php';
require_once __DIR__.'/support.php';
function advanced_actions(): array {return [
    'notifications'=>'handle_notifications','notification_read'=>'handle_notifications','notification_preferences'=>'handle_notifications','notification_preferences_save'=>'handle_notifications',
    'availability'=>'handle_scheduling','schedule_settings'=>'handle_scheduling','schedule_settings_save'=>'handle_scheduling','appointments'=>'handle_scheduling','appointment_book'=>'handle_scheduling','appointment_update'=>'handle_scheduling',
    'plan_workspace'=>'handle_plans','plan_price'=>'handle_plans','plan_save'=>'handle_plans','plan_review'=>'handle_plans',
    'quotations'=>'handle_quotations','quotation'=>'handle_quotations','quotation_save'=>'handle_quotations','quotation_decide'=>'handle_quotations',
    'job_card'=>'handle_jobs','job_save'=>'handle_jobs','job_photo'=>'handle_jobs','job_acknowledge'=>'handle_jobs',
    'forecast'=>'handle_forecast','forecast_settings_save'=>'handle_forecast',
    'support_history'=>'handle_support','support_ask'=>'handle_support','support_handoff'=>'handle_support','support_clear'=>'handle_support'
];}
