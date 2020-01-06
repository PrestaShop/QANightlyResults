<div class="navbar">
    <div class="navbar_container">
        <div class="links">
            <a class="link" href="/"><i class="material-icons">home</i> Home</a>
            <a class="link" href="/graph"><i class="material-icons">timeline</i> Graph</a>
        </div>
        <div class="title">
            <h2>Nightlies report stats</h2>
        </div>
    </div>
</div>
<div class="container">
    <div class="details">
        <div class="options">
            <div class="blocks_container">
                <form action="" method="GET" id="graphform">
                    <div class="form_block">
                        <div class="form_container">
                            <label for="start_date">
                                Start date
                                <input type="date" name="start_date" id="start_date" value="<?php echo $start_date ?>" max="<?php echo date('Y-m-d') ?>"/>
                            </label>
                        </div>
                        <div class="form_container">
                            <label for="end_date">
                                End date
                                <input type="date" name="end_date" id="end_date" value="<?php echo $end_date ?>" max="<?php echo date('Y-m-d') ?>"/>
                            </label>
                        </div>
                    </div><div class="form_block">
                        <label for="version">
                            Version
                            <select name="version" id="version">
                                <?php
                                foreach($versions->result() as $version) {
                                    $s = ($version->version == $selected_version) ? 'selected' : '';
                                    echo '<option value="'.$version->version.'" '.$s.'>'.$version->version.'</option>';
                                }

                                ?>
                            </select>
                        </label>
                    </div><div class="form_block">
                        <label for="Campaign">
                            Campaign
                            <select name="campaign" id="campaign">
                                <option value="">All</option>
                                <?php
                                foreach($campaigns->result() as $campaign) {
                                    $s = ($campaign->campaign == $selected_campaign) ? 'selected' : '';
                                    echo '<option value="'.$campaign->campaign.'" '.$s.'>'.$campaign->campaign.'</option>';
                                }

                                ?>
                            </select>
                        </label>
                    </div><div class="form_block">
                        <button>Filter</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="canvas_container">
            <div class="chart_title">Percentage of tests passed and failed</div>
            <canvas id="chart" style="width: 100%;" height="300"></canvas>
        </div>

        <div class="canvas_container">
            <div class="chart_title">Statistics about test failures</div>
            <canvas id="chart_precise" style="width: 100%;" height="300"></canvas>
            <div class="chart_legend">
                <p><span>Assertion error</span>: Actual result differs from expected.</p>
                <p><span>File not found</span>: Test didn't find the file it was looking for.</p>
                <p><span>Timeout</span>: Object not found after waiting for it to be visible.</p>
                <p><span>Object not found</span>: Selector not valid.</p>
            </div>
        </div>
    </div>
</div>
<script>
    window.onload = function() {

        const opacity = 0.7;

        //first graph
        const data = <?php echo json_encode($graph_data); ?>;

        const labels = Array.from(data, x => x.custom_start_date);
        const passed_percent = Array.from(data, x => Math.floor((parseFloat(x.totalPasses)*10000 / (parseFloat(x.totalPending) + parseFloat(x.totalPasses) + parseFloat(x.totalSkipped) + parseFloat(x.totalFailures))))/100 );
        const failed_percent = Array.from(data, x => Math.floor((parseFloat(x.totalFailures)*10000 / (parseFloat(x.totalPending) + parseFloat(x.totalPasses) + parseFloat(x.totalSkipped) + parseFloat(x.totalFailures))))/100 );
        const skipped_percent = Array.from(data, x => Math.floor((parseFloat(x.totalSkipped)*10000 / (parseFloat(x.totalPending) + parseFloat(x.totalPasses) + parseFloat(x.totalSkipped) + parseFloat(x.totalFailures))))/100 );
        const pending_percent = Array.from(data, x => Math.floor((parseFloat(x.totalPending)*10000 / (parseFloat(x.totalPending) + parseFloat(x.totalPasses) + parseFloat(x.totalPending) + parseFloat(x.totalFailures))))/100 );

        let canvas = document.getElementById('chart');
        let ctx = canvas.getContext('2d');
        let testChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: '% passed',
                        data: passed_percent,
                        backgroundColor: 'rgba(3, 86, 3, '+opacity+')',
                        fill: 'origin'
                    },
                    {
                        label: '% failed',
                        data: failed_percent,
                        backgroundColor: 'rgba(178, 44, 44, '+opacity+')',
                        fill: '-1'
                    },
                  {
                    label: '% pending',
                    data: pending_percent,
                    backgroundColor: 'rgba(30, 160, 255, '+opacity+')',
                    fill: '-2'
                  },
                  {
                    label: '% skipped',
                    data: skipped_percent,
                    backgroundColor: 'rgba(209, 209, 209, '+opacity+')',
                    fill: '-2'
                  }]
            },
            options: {
                scales: {
                    xAxes: [{
                        stacked: true
                    }],
                    yAxes: [{
                        stacked: true,
                    }]
                },
                legend: {
                    display: true,
                    labels: {
                        fontColor: 'rgb(255, 99, 132)'
                    }
                }
            }
        });

        //second graph
        const precise_data = <?php echo json_encode($detailed_graph_data) ?>;
        const p_labels = Array.from(precise_data, x => x.custom_start_date);
        const p_value_expected = Array.from(precise_data, x => x.value_expected);
        const p_file_not_found = Array.from(precise_data, x => x.file_not_found);
        const p_not_visible_after_timeout = Array.from(precise_data, x => x.not_visible_after_timeout);
        const p_wrong_locator = Array.from(precise_data, x => x.wrong_locator);
        const p_other = Array.from(precise_data, x => x.failures - x.value_expected - x.file_not_found - x.not_visible_after_timeout - x.wrong_locator);

        var p_canvas = document.getElementById('chart_precise');
        var p_ctx = p_canvas.getContext('2d');
        var p_testChart = new Chart(p_ctx, {
            type: 'bar',
            data: {
                labels: p_labels,
                datasets: [
                    {
                        label: 'Assertion error',
                        data: p_value_expected,
                        backgroundColor: 'rgba(68, 111, 142, '+opacity+')',
                        fill: 'origin'
                    },
                    {
                        label: 'File not found',
                        data: p_file_not_found,
                        backgroundColor: 'rgba(68, 142, 68, '+opacity+')',
                        fill: '-1'
                    },
                    {
                        label: 'Timeout',
                        data: p_not_visible_after_timeout,
                        backgroundColor: 'rgba(255, 206, 86, '+opacity+')',
                        fill: '-2'
                    },
                    {
                        label: 'Object not found',
                        data: p_wrong_locator,
                        backgroundColor: 'rgba(153, 102, 255, '+opacity+')',
                        fill: '-3'
                    },
                    {
                        label: 'Other',
                        data: p_other,
                        backgroundColor: 'rgba(0, 0, 0, '+opacity+')',
                        fill: '-6'
                    }]
            },
            options: {
                scales: {
                    xAxes: [{
                        stacked: true
                    }],
                    yAxes: [{
                        stacked: true,
                        ticks: {
                            min: 0
                        }
                    }]
                },
                legend: {
                    display: true,
                    labels: {
                        fontColor: 'rgb(255, 99, 132)'
                    }
                }
            }
        });

        canvas.onclick = function(e) {
            let slice = testChart.getElementAtEvent(e);
            if (!slice.length) return; // return if not clicked on slice
            let label = slice[0]._model.label;
            let item = data.find(function(element) {
                return element.custom_start_date == label;
            });
            window.open('/report/'+item.id, '_blank');
        };
    }
</script>
