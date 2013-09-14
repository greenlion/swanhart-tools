<?php
/**
 * DooPager class file.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2009 Leng Sheng Hong
 * @license http://www.doophp.com/license
 */

/**
 * A helper class to help generating pagination widget when displaying a list of items.
 *
 * You can change every component of the pagination widget through CSS. The CSS class name for the components can be changed if needed.
 *
 * <p>You can send GET, POST, PUT and DELETE requests with DooRestClient.
 * Method chaining is also supported by this class.</p>
 * Example usage:
 * <code>
 * Doo::loadHelper('DooPager');
 * Doo::loadModel('User');
 *
 * $page = $this->params['pindex'];
 * $total = User::getTotal();
 * $pager = new DooPager(Doo::conf()->APP_URL.'main/gallery', $total, 6, 10);
 *
 * #change the text of next/prev
 * //$pager->prevText = 'prev';
 * //$pager->nextText = 'next';
 *
 * #If you don't need the next/prev links
 * //$pager->noNextPrev();
 *
 * #Do the pagination
 * $pager->paginate($page);
 *
 * $limit = $pager->limit;
 * Doo::db()->find('User', array('limit'=>$limit));
 *
 * echo $pager->defaultCss;
 * //echo $pager->components['prev_link'];
 * echo $pager->output;
 * echo $pager->components['jump_menu'];
 * echo $pager->components['page_size'];
 * //echo $pager->components['next_link'];
 * </code>
 * 
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @version $Id: DooPager.php 1000 2009-08-21 15:27:22
 * @package doo.helper
 * @since 1.0
 */
class DooPager{
    /**
     * CSS class name for the Pages links
     * @var string
     */
    public $pagesCss='paginate';

    /**
     * CSS class name for the Pages DropDown menu
     * @var string
     */
    public $dropDownCss = 'pagerDropDown';

    /**
     * CSS class name for the page sizes menu
     * @var string
     */
    public $pageSizeCss = 'pageSize';

    /**
     * CSS class name for inactive links
     * @var string
     */
    public $inactivePrevCss = 'inactivePrev';

    /**
     * CSS class name for inactive links
     * @var string
     */
    public $inactiveNextCss = 'inactiveNext';

    /**
     * CSS class name for selected current link
     * @var string
     */
    public $currentCss = 'current';

    /**
     * CSS class name for next link
     * @var string
     */
    public $nextCss = 'next';

    /**
     * CSS class name for previous link
     * @var string
     */
    public $prevCss = 'prev';

    /**
     * Text for the Previous link
     * @var string
     */
    public $prevText = '&laquo;';

    /**
     * Text for the Next button link
     * @var string
     */
    public $nextText = '&raquo;';

    /**
     * Contain the list of components to be used in view. (pages, jump menu, page_size, current_page, total_page)
     * @var array
     */
    public $components;

    /**
     * Items to be displayed per page
     * @var int
     */
    public $itemPerPage=10;

    /**
     * The current page number
     * @var int
     */
    public $currentPage = 1;

    /**
     * Maximum Pager length
     * @var int
     */
    public $maxLength = 10;

    /**
     * Total items to be split in the pagination
     * @var int
     */
    public $totalItem;

    /**
     * Total pages in the pagination
     * @var int
     */
    public $totalPage;


    /**
     * The pages HTML output
     * @var string
     */
    public $output;

    /**
     * The URL prefix for all the pagination links
     * @var string
     */
    public $baseUrl;

    //----- for SQL use -----
    /**
     * Position of the record to begin the pagination LIMIT query
     * @var string
     */
    public $low;
    
    /**
     * Position of the record to end the pagination LIMIT query
     * @var string
     */
    public $high;

    /**
     * To be use with the pagination LIMIT query LIMIT $limit
     * @var string
     */
    public $limit;

    /**
     * To display/hide the next/prev button links
     * @var bool
     */
     private $_noNextPrev=false;

    /**
     * Default css for testing
     * @var string
     */
    public $defaultCss = '<style>
.paginate, .current, .inactivePrev, .inactiveNext, .prev, .next {
    font-family: Verdana,Helvetica,Arial,sans-serif;
    font-size:12px;
    border:1px solid #D8D8D8;
    float:left;
    height:20px;
    line-height:20px;
    margin-right:2px;
    overflow:hidden;
    padding:0 6px;
    text-decoration:none;
}
a:hover .paginate{
    border-color:#006699;
}

.current {
    border-color:#006699;
    background-color:#006699;
    color:#fff;
    font-weight:bold;
}
.inactivePrev, .inactiveNext{
    color:#999;
}
</style>';

    /**
     * Instanciate the Pager object
     *
     * @param string $baseURL Base URL to be appended to the page number
     * @param int $totalItems Total items to be paginate
     * @param int $itemPerPage Items to be shown in one page.
     * @param int $maxlength Number of links for the pager navigation
     * @param string $prevText Text for the Previous button link
     * @param string $nextText Text for the Next button link
     */
    function  __construct($baseURL='', $totalItems=120, $itemPerPage=10, $maxlength=10, $prevText='&laquo;', $nextText='&raquo;'){
        $this->baseUrl = $baseURL;
        $this->totalItem = $totalItems;
        $this->maxLength = $maxlength;
        $this->itemPerPage = $itemPerPage;
        $this->prevText = $prevText;
        $this->nextText = $nextText;
    }

    /**
     * Hide next & prev links from the output
     */
    public function noNextPrev(){
        $this->_noNextPrev = true;
    }

    /**
     * Set the CSS class name for the pager components
     *
     * @param string $pagesName
     * @param string $inactiveName
     * @param string $currentName
     * @param string $nextName
     * @param string $prevName
     * @param string $dropDownName
     * @param string $pageSizeName
     */
    public function setCss($pagesName='paginate', $inactiveName='inactive', $currentName='current', $nextName='next', $prevName='prev', $dropDownName='pagerDropDown', $pageSizeName='pageSize'){
        $this->pagesCss = $pagesName;
        $this->inactiveCss = $inactiveName;
        $this->currentCss = $currentName;
        $this->nextCss = $nextName;
        $this->prevCss = $prevName;
        $this->dropDownCss = $dropDownName;
        $this->pageSizeCss = $pageSizeName;
    }

    /**
     * Paginate the list of items and prepare pager components to be use in View.
     * 
     * @param int $page The current page number
     * @param int $itemPerPage Items per page
     * @return array An array of pager component, access via <strong>pages, jump_menu, page_size, current_page, total_page, next_link, prev_link</strong>
     */
    public function paginate($page, $itemPerPage=0){
        if($itemPerPage===0){
            $itemPerPage = $this->itemPerPage;
        }else{
            $this->itemPerPage = $itemPerPage;
        }

        $this->totalPage = ceil($this->totalItem/$itemPerPage);

        $this->currentPage = (int) $page; 

        if($this->currentPage < 1 || !is_numeric($this->currentPage))
            $this->currentPage = 1;

        if($this->currentPage > $this->totalPage)
            $this->currentPage = $this->totalPage;

        $prev_page = $this->currentPage-1;
        $next_page = $this->currentPage+1;

        if($this->_noNextPrev)
            $this->components['prev_link'] = ($this->currentPage != 1 && $this->totalItem >= $this->maxLength) ? "<a class=\"{$this->prevCss}\" href=\"{$this->baseUrl}/{$prev_page}\">{$this->prevText}</a> ":"<span class=\"{$this->inactivePrevCss}\">{$this->prevText}</span> ";
        else
            $this->output = ($this->currentPage != 1 && $this->totalItem >= $this->maxLength) ? "<a class=\"{$this->prevCss}\" href=\"{$this->baseUrl}/{$prev_page}\">{$this->prevText}</a> ":"<span class=\"{$this->inactivePrevCss}\">{$this->prevText}</span> ";

        if($this->totalPage > $this->maxLength){
            $midRange = $this->maxLength-2;
            
            $start_range = $this->currentPage - floor($midRange/2);
            $end_range = $this->currentPage + floor($midRange/2);

            if($start_range <= 0){
                $end_range += abs($start_range)+1;
                $start_range = 1;
            }

            if($end_range > $this->totalPage){
                $start_range -= $end_range-$this->totalPage;
                $end_range = $this->totalPage;
            }

            while($end_range-$start_range+1<$this->maxLength-1){
                $end_range++;
            }
            
            $modulus = (int) $this->maxLength%2==0;
            $center = floor($this->maxLength/2);
                
            if($this->currentPage > $center ){
                $end_range --;
            }
            
            if($modulus==0 && $this->totalPage - $this->currentPage+1 <= $center){
                while($end_range-$start_range+1<$this->maxLength-1){
                    $start_range--;
                }
            }
            $range = range($start_range,$end_range);

            for($i=1;$i<=$this->totalPage;$i++){
                // loop through all pages. if first, last, or in range, display
                if($i==1 || $i==$this->totalPage || in_array($i,$range)){
                    $lastDot = '';

                    if($modulus==1){
                        if($i==$this->totalPage && $this->currentPage<($this->totalPage-($this->maxLength-$center-$modulus)))
                            $lastDot = '...';
                    }else{
                        if($i==$this->totalPage && $this->currentPage<=($this->totalPage-($this->maxLength-$center)))
                            $lastDot = '...';
                    }
                    $this->output .= ($i == $this->currentPage) ? "<a class =\"{$this->currentCss}\" href=\"javascript:void(0);\">$i":"<a class=\"{$this->pagesCss}\"  href=\"{$this->baseUrl}/$i\">$lastDot $i";

                    if($range[0] > 2 && $i == 1)
                        $this->output .= " ...</a> ";
                    else
                        $this->output .= '</a> ';
                }
            }

            if($this->_noNextPrev)
                $this->components['next_link'] = ($this->currentPage != $this->totalPage && $this->totalItem >= $this->maxLength) ? "<a class=\"{$this->nextCss}\" href=\"{$this->baseUrl}/$next_page\">{$this->nextText}</a>\n" : "<span class=\"{$this->inactiveNextCss}\">{$this->nextText}</span>\n";
            else
                $this->output .= ($this->currentPage != $this->totalPage && $this->totalItem >= $this->maxLength) ? "<a class=\"{$this->nextCss}\" href=\"{$this->baseUrl}/$next_page\">{$this->nextText}</a>\n" : "<span class=\"{$this->inactiveNextCss}\">{$this->nextText}</span>\n";
        }
        else{
            for($i=1;$i<=$this->totalPage;$i++){
                $this->output .= ($i == $this->currentPage) ? "<a class=\"{$this->currentCss}\" href=\"javascript:void(0);\">$i</a> ":"<a class=\"{$this->pagesCss}\" href=\"{$this->baseUrl}/$i\">$i</a> ";
            }

            if($this->_noNextPrev)
                $this->components['next_link'] = ($this->currentPage != $this->totalPage && $this->totalItem >= $this->maxLength) ? "<a class=\"{$this->nextCss}\" href=\"{$this->baseUrl}/$next_page\">{$this->nextText}</a>\n" : "<span class=\"{$this->inactiveNextCss}\">{$this->nextText}</span>\n";
            else
                $this->output .= ($this->currentPage != $this->totalPage && $this->totalItem >= $this->maxLength) ? "<a class=\"{$this->nextCss}\" href=\"{$this->baseUrl}/$next_page\">{$this->nextText}</a>\n" : "<span class=\"{$this->inactiveNextCss}\">{$this->nextText}</span>\n";
        }

        $this->low = ($this->currentPage-1) * $this->itemPerPage;
        $this->high = ($this->currentPage * $this->itemPerPage)-1;
        $this->limit = " $this->low,$this->itemPerPage";

        $this->components['pages'] = $this->output;
        $this->components['jump_menu'] = $this->showJumpMenu();
        $this->components['page_size'] = $this->showPageSize();
        $this->components['current_page'] = $this->currentPage;
        $this->components['total_page'] = $this->totalPage;

        return $this->components;
    }

    /**
     * Generate a Drop down menu for different page sizes.
     *
     * @param array $sizeArr Array of page size
     * @return string Drop down menu for the page sizes
     */
    public function showPageSize($sizeArr=null){
        $items = '';
        if($sizeArr==null)
            $ipp_array = array(10,25,50,100);

        foreach($ipp_array as $ipp_opt)
            $items .= ($ipp_opt == $this->itemPerPage) ? "<option selected value=\"$ipp_opt\">$ipp_opt</option>\n":"<option value=\"$ipp_opt\">$ipp_opt</option>\n";

        return "<select class=\"{$this->pageSizeCss}\" onchange=\"window.location='{$this->baseUrl}/1/'+this[this.selectedIndex].value; return false\">$items</select>\n";
    }

    /**
     * Generate a Drop down menu for all page numer
     *
     * @param bool $withPageSize Navigate with page size
     * @return string Drop down menu for all page numer
     */
    public function showJumpMenu($withPageSize=false){
        $option = '';
        for($i=1;$i<=$this->totalPage;$i++){
            $option .= ($i==$this->currentPage) ? "<option value=\"$i\" selected>$i</option>\n":"<option value=\"$i\">$i</option>\n";
        }
        if($withPageSize)
            return "<select class=\"{$this->dropDownCss}\" onchange=\"window.location='{$this->baseUrl}/'+this[this.selectedIndex].value+'/$this->itemPerPage';return false\">$option</select>\n";
        return "<select class=\"{$this->dropDownCss}\" onchange=\"window.location='{$this->baseUrl}/'+this[this.selectedIndex].value;return false\">$option</select>\n";
    }

}
