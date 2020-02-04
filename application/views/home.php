<div class="navbar">
    <div class="navbar_container">
        <div class="links">
            <a class="link" href="/"><i class="material-icons">home</i> Home</a>
            <a class="link" href="/graph"><i class="material-icons">timeline</i> Graph</a>
        </div>
        <div class="title">
            <h2>Nightlies reports</h2>
        </div>
    </div>
</div>
<div class="container">
    <div class="details">
        <div class="table_container">
            <table class="table">
                <thead>
                <tr>
                    <th></th>
                    <th>Date</th>
                    <th>Version</th>
                    <th>Duration</th>
                    <th>Content</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php
                    if (sizeof($execution_list) > 0) {
                    foreach($execution_list as $date => $executions) {
                        foreach($executions as $execution) {
                            $content = '';
                            if (is_object($execution)) {
                                if ($execution->passes > 0) {
                                    $content .= '<div class="content_block count_passed" title="Tests passed"><i class="material-icons icon icon-passed">check</i> ' . $execution->passes . '</div>';
                                }
                                if ($execution->failures > 0) {
                                    $content .= '<div class="content_block count_failed" title="Tests failed"><i class="material-icons icon icon-failed">clear</i> ' . $execution->failures . '</div>';
                                }
                                if ($execution->pending > 0) {
                                    $content .= '<div class="content_block count_pending" title="Tests pending"><i class="material-icons icon icon-pending">pause</i> ' . $execution->pending . '</div>';
                                }
                                $download_link = '';
                                if (count($gcp_files_list) > 0) {
                                    preg_match('/([0-9]{4}-[0-9]{2}-[0-9]{2})-[A-z0-9\.]*?\.json/', $execution->filename, $matches_filename);
                                    if (isset($matches_filename[1])) {
                                        $date_from_filename = $matches_filename[1];
                                        $pattern = '/' . $date_from_filename . '-' . $execution->version . '-prestashop_([A-z0-9\.?]*)\.zip/';
                                        foreach ($gcp_files_list as $gcp_file) {
                                            preg_match($pattern, $gcp_file, $matches);
                                            if (isset($matches[1]) && $matches[1] != '') {
                                                $branch = $matches[1];
                                                $download_link = '<a href="' . $gcp_url . $gcp_file . '"><i class="material-icons">cloud_download</i> Download build</a>';
                                                break;
                                            }
                                        }
                                    }
                                }

                                $comparison = '';
                                if ($execution->broken_since_last != '') {
                                    $comparison = '
                                        <span class="comparison equal" title="Since the last report, '.$execution->equal_since_last.' reports are still failing">
                                        <i class="material-icons icon">trending_flat</i>'.$execution->equal_since_last.'</span>
                                        <span class="comparison fixed" title="Since the last report, '.$execution->fixed_since_last.' reports are fixed">
                                        <i class="material-icons icon">trending_up</i>'.$execution->fixed_since_last.'</span>
                                        <span class="comparison broken" title="Since the last report, '.$execution->broken_since_last.' reports are now broken">
                                        <i class="material-icons icon">trending_down</i>'.$execution->broken_since_last.'</span>
                                    ';
                                }

                                echo '<tr class="version_' . str_replace('.', '', $execution->version) . '">';
                                echo '<td><a href="/report/' . $execution->id . '" target="_blank"><i class="material-icons">visibility</i> Show report</a></td>';
                                echo '<td>' . date('d/m/Y', strtotime($execution->start_date)) . '</td>';
                                echo '<td>' . $execution->version . '</td>';
                                echo '<td class="align-left">' . date('H:i', strtotime($execution->start_date)) . ' - ' . date('H:i', strtotime($execution->end_date)) . ' (' . duration($execution->duration / 1000) . ')</td>';
                                echo '<td class="align-left">' . $content . '<div class="compare">'.$comparison.'</div></td>';
                                echo '<td>' . $download_link . '</td>';
                                echo '</tr>';
                            } else {
                                //no object, just a link to GCP
                                preg_match('/([0-9]{4}-[0-9]{2}-[0-9]{2})-([A-z0-9\.]*)-prestashop_(.*)\.zip/', $execution, $matches_filename);
                                echo '<tr>';
                                echo '<td>No tests found</td>';
                                echo '<td>' . date('d/m/Y', strtotime($date)) . '</td>';
                                echo '<td>'.$matches_filename[2].'</td>';
                                echo '<td>-</td>';
                                echo '<td>-</td>';
                                echo '<td><a href="' . $gcp_url . $execution .'"><i class="material-icons">cloud_download</i> Download build</a></td>';
                                echo '</tr>';
                            }
                        }
                    }
                }
                ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
