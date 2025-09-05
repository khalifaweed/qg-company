<?php
if (!defined('ABSPATH')) {
    exit;
}

$publisher_id = \WhatJobsFeeder\Core::get_option('publisher_id');
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Feeds Automáticos', 'whatjobs-feeder'); ?></h1>
    <a href="<?php echo add_query_arg(['action' => 'new'], admin_url('admin.php?page=whatjobs-feeder')); ?>" class="page-title-action">
        <?php _e('Adicionar Novo', 'whatjobs-feeder'); ?>
    </a>
    <hr class="wp-header-end">

    <?php settings_errors('whatjobs_feeder'); ?>

    <?php if (empty($publisher_id)): ?>
        <div class="notice notice-warning">
            <p>
                <?php 
                printf(
                    __('Você precisa configurar seu Publisher ID nas %s antes de criar feeds.', 'whatjobs-feeder'),
                    '<a href="' . admin_url('admin.php?page=whatjobs-feeder-settings') . '">' . __('configurações', 'whatjobs-feeder') . '</a>'
                );
                ?>
            </p>
        </div>
    <?php endif; ?>

    <div class="whatjobs-feeder-stats">
        <div class="stats-box">
            <h3><?php _e('Estatísticas Rápidas', 'whatjobs-feeder'); ?></h3>
            <?php
            global $wpdb;
            $total_feeds = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}whatjobs_feeds");
            $active_feeds = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}whatjobs_feeds WHERE status = 'active'");
            $imported_jobs = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_whatjobs_feeder_imported' AND meta_value = '1'");
            ?>
            <ul>
                <li><?php printf(__('Total de feeds: %d', 'whatjobs-feeder'), $total_feeds); ?></li>
                <li><?php printf(__('Feeds ativos: %d', 'whatjobs-feeder'), $active_feeds); ?></li>
                <li><?php printf(__('Vagas importadas: %d', 'whatjobs-feeder'), $imported_jobs); ?></li>
            </ul>
        </div>
    </div>

    <form method="post">
        <?php
        $feeds_table->display();
        ?>
    </form>
</div>

<style>
.whatjobs-feeder-stats {
    margin: 20px 0;
}

.stats-box {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 15px;
    max-width: 300px;
}

.stats-box h3 {
    margin-top: 0;
    margin-bottom: 10px;
}

.stats-box ul {
    margin: 0;
    padding-left: 20px;
}

.stats-box li {
    margin-bottom: 5px;
}

.status-active {
    color: #46b450;
    font-weight: bold;
}

.status-inactive {
    color: #dc3232;
    font-weight: bold;
}
</style>