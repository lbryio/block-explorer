<?php if (isset($numPages) && $numPages > 1): ?>
    <div class="pagination">
        <div class="prev">
            &nbsp;
            <?php if ($currentPage > 1): ?>
            <a href="?page=<?php echo $currentPage - 1 ?>">Previous</a>
            <?php endif; ?>
        </div>
        <div class="pages">
            <?php if ($numRecords > 0):

                $start = $numPages > 1 ? 1 : 0;
                $end = $numPages > 1 ? min($numPages, 10) : 0;
                // use currentPage as the starting point
                if ($numPages > 10) {
                    if ($currentPage > 5) {
                        $start = $currentPage < 10 ? 1 : $currentPage - 5;
                        $end = ($currentPage > ($numPages - 10) && $start > 5) ? $numPages : min($currentPage + 5, $numPages);
                    }
                }
            ?>

            <?php if ($start >= 5): ?>
                <div class="page-number"><a href="?page=1">1</a></div>
                <div class="page-number">...</div>
            <?php endif; ?>

            <?php
            if ($start > 0):
                for ($i = $start; $i <= $end; $i++):
            ?>
            <div class="page-number">
                <?php if ($currentPage == $i): echo $i; else: ?>
                <a href="?page=<?php echo $i ?>"><?php echo $i ?></a>
                <?php endif; ?>
            </div>
            <?php
                endfor;
            endif;
            ?>
            <?php if ($end < $numPages - 1): ?>
                <div class="page-number">...</div>
                <div class="page-number">
                    <a href="?page=<?php echo $numPages ?>"><?php echo $numPages ?></a>
                </div>
            <?php endif; ?>
            <?php
            endif; ?>
        </div>
        <div class="next">
            &nbsp;
            <?php if ($currentPage < $numPages): ?>
            <a href="?page=<?php echo $currentPage + 1 ?>">Next</a>
            <?php endif; ?>
        </div>
        <div class="clear"></div>
    </div>
<?php endif; ?>