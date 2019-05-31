<div class="navbar">
    <div class="navbar_container">
        <div class="links">
            <a class="link" href="/"><i class="material-icons">home</i> Home</a>
            <a class="link" href="/graph"><i class="material-icons">timeline</i> Graph</a>
        </div>
        <div class="title">
            <h2>Report</h2>
        </div>
        <div class="recap">

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
                    {{PRECISE_STATS}}
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

                            //echo '</section>'; //closing the file container section
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

                        echo '<div class="file_title" data-state="empty" data-campaign="'.$cur_campaign.'" data-file="'.($item->file).'" title="Click to load data">
                        <a name="'.$item->file.'"></a>
                            <h3><i class="material-icons">assignment</i> '.$item->file.' '.$indicators.'</h3>
                            <section class="container_file">
                            </section>
                         </div>';
                    }
                    //echo '</section>'; //closing the file container section
                    echo '</article>'; //closing the article container
                }
            ?>
        </div>

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
        $('.file_title').click(function() {
            let button = $(this);
            let campaign = $(this).data('campaign');
            let file = $(this).data('file');
            let data = {'campaign': campaign, 'file': file, 'execution_id': <?php echo $execution->id ?>};
            if ($(this).attr('data-state') === 'empty') {
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
                        button.attr('title', "Click to toggle the view");
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