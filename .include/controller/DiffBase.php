<?php
namespace GitPHP\Controller;
/**
 * GitPHP Controller DiffBase
 *
 * Base controller for diff-type views
 *
 * @author Christopher Han <xiphux@gmail.com>
 * @copyright Copyright (c) 2011 Christopher Han
 * @package GitPHP
 * @subpackage Controller
 */


/**
 * Constants for diff modes
 */
define('GITPHP_DIFF_UNIFIED', 1);
define('GITPHP_DIFF_SIDEBYSIDE', 2);

/**
 * Constant of the diff mode cookie in the user's browser
 */
define('GITPHP_DIFF_MODE_COOKIE', 'GitPHPDiffMode');

/**
 * Diff mode cookie lifetime
 */
define('GITPHP_DIFF_MODE_COOKIE_LIFETIME', 60 * 60 * 24 * 365);           // 1 year

/**
 * DiffBase controller class
 *
 * @package GitPHP
 * @subpackage Controller
 */
abstract class DiffBase extends Base
{
    /**
     * ReadQuery
     *
     * Read query into parameters
     *
     * @access protected
     */
    protected function ReadQuery()
    {
        if (!isset($this->params['plain']) || $this->params['plain'] != true) {
            if ($this->DiffMode(isset($_GET['o']) ? $_GET['o'] : '') == GITPHP_DIFF_SIDEBYSIDE) {
                $this->params['sidebyside'] = true;
            }
        }
    }

    /**
     * DiffMode
     *
     * Determines the diff mode to use
     *
     * @param string $overrideMode mode overridden by the user
     * @access protected
     * @return int
     */
    protected function DiffMode($overrideMode = '')
    {
        $mode = GITPHP_DIFF_UNIFIED;    // default

        /*
    	 * Check cookie
    	 */
        if (!empty($_COOKIE[GITPHP_DIFF_MODE_COOKIE])) {
            $mode = $_COOKIE[GITPHP_DIFF_MODE_COOKIE];
        } else {
            /*
    		 * Create cookie to prevent browser delay
    		 */
    		            setcookie(GITPHP_DIFF_MODE_COOKIE, $mode, time() + GITPHP_DIFF_MODE_COOKIE_LIFETIME);
        }

        if (!empty($overrideMode)) {
            /*
    		 * User is choosing a new mode
    		 */
            if ($overrideMode == 'sidebyside') {
                $mode = GITPHP_DIFF_SIDEBYSIDE;
                setcookie(GITPHP_DIFF_MODE_COOKIE, GITPHP_DIFF_SIDEBYSIDE, time() + GITPHP_DIFF_MODE_COOKIE_LIFETIME);
            } else if ($overrideMode == 'unified') {
                $mode = GITPHP_DIFF_UNIFIED;
                setcookie(GITPHP_DIFF_MODE_COOKIE, GITPHP_DIFF_UNIFIED, time() + GITPHP_DIFF_MODE_COOKIE_LIFETIME);
            }
        }

        return $mode;
    }

    /**
     * LoadHeaders
     *
     * Loads headers for this template
     *
     * @access protected
     */
    protected function LoadHeaders()
    {
        if (isset($this->params['plain']) && ($this->params['plain'] === true)) {
            \GitPHP_Log::GetInstance()->SetEnabled(false);
            $this->headers[] = 'Content-type: text/plain; charset=UTF-8';
        }
    }

    protected function LoadData()
    {
        $this->tpl->assign('sidebyside', isset($this->params['sidebyside']) && ($this->params['sidebyside'] === true));
    }

    protected function loadReviewsLinks(\GitPHP_Commit $co, $ticket)
    {
        if (preg_match('#([A-Z]+-[0-9]+)#', $ticket, $m)) {
            $ticket = $m[1];
        }
        $Db = \GitPHP_Db::getInstance();
        $reviews = $Db->getReview($ticket, $co->GetHash());

        $comments_count = $Db->getCommentsCountForReviews(array_keys($reviews), $this->Session->getUser()->getId());
        foreach ($comments_count as $review_id => $row) {
            if (empty($row['cnt']) && empty($row['cnt_draft'])) {
                unset($reviews[$review_id]);
                continue;
            }
            $reviews[$review_id]['comments_count'] = $row['cnt'];
            $reviews[$review_id]['comments_count_draft'] = $row['cnt_draft'];
            $reviews[$review_id]['ticket'] = $row['ticket'];
        }

        if (isset($reviews[$this->params['review']])) {
            unset($reviews[$this->params['review']]);
        }

        foreach ($reviews as $review_id => &$review) {
            $review['link'] = \GitPHP_Application::getUrl('reviews', ['review' => $review_id]);
            $review['diff_link'] = '';
            if ($review['hash_base'] && $review['hash_base'] != 'blob' && isset($this->params['branch']) && !preg_match('#^[0-9a-f]{40}$#', $this->params['branch'])) {
                $review['diff_link'] = \GitPHP_Application::getUrl(
                    'branchdiff',
                    [
                        'p' => $this->project->GetProject(),
                        'branch' => $this->params['branch'],
                        'base' => $review['hash_head'],
                    ]
                );
            }
        }
        $this->tpl->assign('reviews', $reviews);
    }

    protected function filterRootFolders($folders)
    {
        $allowed = [
            'UTests',
            'testlib',
            'EDL',
        ];

        return array_intersect($folders, $allowed);
    }
}
