<div class="navbar">
    <div class="navbar_container">
        <div class="links">
            <a class="link" href="/"><i class="material-icons">home</i> Home</a>
            <a class="link" href="/graph"><i class="material-icons">timeline</i> Graph</a>
        </div>
        <div class="title">
            <h2>Report - <?php echo date('d/m/Y', strtotime($execution->start_date)); ?></h2>
        </div>
        <div class="recap">

            <div class="recap_block suites" title="Version <?php echo $execution->version; ?>">
                <i class="material-icons">local_offer</i> <span><?php echo $execution->version; ?></span>
            </div><div class="recap_block suites" title="Execution time">
                <i class="material-icons">timer</i> <span><?php echo duration($execution->duration / 1000); ?></span>
            </div>
            <div class="recap_block suites" title="Number of suites">
                <i class="material-icons">library_books</i> <span><?php echo $execution->suites; ?></span>
            </div>
            <div class="recap_block tests" title="Number of tests">
                <i class="material-icons">assignment</i> <span><?php echo $execution->tests; ?></span>
            </div>
            <div class="recap_block passed_tests" title="Number of passed tests">
                <i class="material-icons">check_circle_outline</i> <span><?php echo $execution->passes; ?></span>
            </div>
                <?php
                if ($execution->failures > 0) {
                    echo '<div class="recap_block failed_tests" title="Number of failed tests">
                <i class="material-icons">highlight_off</i> <span>'.$execution->failures.'</span>
            </div>';
                }

                if ($execution->skipped > 0) {
                    echo '<div class="recap_block skipped_tests" title="Number of skipped tests">
                <i class="material-icons">radio_button_checked</i> <span>'.$execution->skipped.'</span>
            </div>';
                }
            ?>
        </div>
    </div>
</div>
<div class="container">

    <div class="details">
        <div class="options">
            <div class="blocks_container">
                <div class="block">
                    Start Date : <?php echo date('d/m/Y H:i', strtotime($execution->start_date)); ?>
                </div>
                <div class="block">
                    End Date : <?php echo date('d/m/Y H:i', strtotime($execution->end_date)); ?>
                </div>
            </div>
        </div>
        <div id="left_navigation">
            <div class="navigation_block">
                <h4>Options</h4>
                <div class="buttons">
                    <div class="button">
                        <button id="toggle_failed" data-state="shown">Hide Passed Tests</button>
                    </div>
                </div>
            </div>
            <hr>
            <div class="navigation_block">
                <h4>Navigation</h4>
                <div class="navigation">
                    <?php
                    if ($summaryData->num_rows() > 0) {
                        $cur_campaign = $summaryData->row()->campaign;
                        echo '<div id="campaign_list">';
                        echo '<a href="#'.$cur_campaign.'"><div class="campaign">'.$cur_campaign.'</div></a>';
                        echo '<div class="file_list">';
                        foreach($summaryData->result() as $item) {
                            if ($cur_campaign != $item->campaign) {
                                $cur_campaign = $item->campaign;
                                echo '</div>'; //closing the file list
                                echo '<a href="#'.$cur_campaign.'"><div class="campaign">'.$cur_campaign.'</div></a>';
                                echo '<div class="file_list">';
                            }
                            $class = 'passed';
                            if ($item->hasFailed > 0) {
                                $class = 'failed';
                            }
                            echo '<a href="#'.$item->file.'"><div class="file '.$class.'"> '.$item->file.'</div></a>';
                            //listing files in it
                        }
                        echo '</div>'; //closing the file list
                        echo '</div>'; //closing the campaign list
                    }

                    ?>
                </div>
            </div>
            <hr>
            <div class="navigation_block">
                <div class="additional_infos">
                    <h4>Additional Info</h4>
                    <ul class="precise_stats">
                        <li><span>Assertion Error : </span><?php echo $details->value_expected; ?></li>
                        <li><span>File not found : </span><?php echo $details->file_not_found; ?></li>
                        <li><span>Timeout : </span><?php echo $details->not_visible_after_timeout; ?></li>
                        <li><span>Object not found : </span><?php echo $details->wrong_locator; ?></li>
                        <li><span>Invalid Session ID : </span><?php echo $details->invalid_session_id; ?></li>
                        <li><span>Chrome not reachable : </span><?php echo $details->chrome_not_reachable; ?></li>
                    </ul>
                </div>
            </div>
        </div>
        <div id="content">
            <?php
                if ($summaryData->num_rows() > 0) {
                    $cur_campaign = $summaryData->row()->campaign;
                    echo '<div class="campaign_title" id="'.$cur_campaign.'">
               <a name="'.$cur_campaign.'"></a>
        <h2><i class="material-icons">library_books</i> '.$cur_campaign.'</h2>
        </div>';
                    echo '<article class="container_campaign" id="campaign_'.$cur_campaign.'">';
                    foreach($summaryData->result() as $item) {
                        if ($cur_campaign != $item->campaign) {
                            $cur_campaign = $item->campaign;

                            echo '</article>'; //closing the article container
                            echo '<a name="'.$cur_campaign.'"></a>';
                            echo '<div class="campaign_title" id="'.$cur_campaign.'">
                <h2><i class="material-icons">library_books</i> '.$cur_campaign.'</h2>
                </div>';
                            echo '<article class="container_campaign" id="campaign_'.$cur_campaign.'">';
                        }
                        //file part
                        $indicators = '';
                        if ($item->hasFailed > 0) {
                            $indicators = '<span class="indicator failed" title="Tests failed">('.$item->hasFailed.')</span>';
                        }

                        echo '<div class="file_title" data-state="empty" data-campaign="'.$cur_campaign.'" data-file="'.($item->file).'" >
                        <a name="'.$item->file.'"></a>
                            <h3 title="Click to load data"><i class="material-icons">assignment</i> '.$item->file.' '.$indicators.'</h3>
                            <section class="container_file">
                            </section>
                         </div>';
                    }
                    echo '</article>'; //closing the article container
                } else {
                    echo '<h4>No data found</h4>';
                }
            ?>
        </div>
        <div style="clear:both"></div>
    </div>
</div>
<script>
    $(document).ready(function() {

        $('body').on('click', '.test_title', function() {
            let id = $(this).attr('id');
            $('#stack_'+id).slideToggle('fast');
        });

        $('body').on('click', '#toggle_failed', function() {
            var state = $(this).attr('data-state');

            if (state === 'shown') {
                $('section.suite.hasPassed:not(.hasFailed)').hide();
                $('section.test_component.passed').hide();
                $(this).html('Show Passed Tests');
                $(this).attr('data-state', 'hidden');
            } else {
                $('section.suite.hasPassed:not(.hasFailed)').show();
                $('section.test_component.passed').show();
                $(this).html('Hide Passed Tests');
                $(this).attr('data-state', 'shown');
            }
        });

        //auto loader
        $('.file_title>h3').click(function() {
            let button = $(this).parent('.file_title');
            const that = $(this);
            let campaign = button.data('campaign');
            let file = button.data('file');
            let data = {'campaign': campaign, 'file': file, 'execution_id': <?php echo $execution->id ?>};
            if (button.attr('data-state') === 'empty') {
                button.children('.container_file').hide().html('<div class="ajaxloader"><img src="/public/assets/images/ajax-loader.gif"/></div>').show();
                $.ajax({
                    url: "/report/getSuiteData",
                    dataType: "JSON",
                    data: data,
                    method: 'GET',
                    success: function(response) {
                        console.log(button.closest('.container_file'));
                        button.children('.container_file').hide().html(response).fadeIn('fast');
                        button.attr('data-state', 'loaded');
                        that.attr('title', "Click to toggle the view");
                    },
                    error: function(response) {
                        alert("Loading failed. Try again in a few moments.");
                    },
                    timeout: function(response) {
                        alert("Timeout. Server might be overloaded. Contact an administrator.");
                    }
                });
            } else {
                button.children('.container_file').toggle();
            }
        });
    });
</script>