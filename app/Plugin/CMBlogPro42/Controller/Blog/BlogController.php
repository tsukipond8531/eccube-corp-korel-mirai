<?php

namespace Plugin\CMBlogPro42\Controller\Blog;


use Eccube\Controller\AbstractController;
use Knp\Component\Pager\PaginatorInterface;
use Plugin\CMBlogPro42\Entity\Blog;
use Plugin\CMBlogPro42\Entity\BlogStatus;
use Plugin\CMBlogPro42\Form\Type\Admin\BlogType;
use Plugin\CMBlogPro42\Repository\BlogRepository;
use Plugin\CMBlogPro42\Repository\CategoryRepository;
use Plugin\CMBlogPro42\Repository\ConfigRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Eccube\Repository\PageRepository;
use Eccube\Repository\ProductRepository;
use Symfony\Component\HttpFoundation\Response;

use Customize\Entity\BrowsedBlog;
use Customize\Repository\BrowsedBlogRepository;

class BlogController extends AbstractController
{
    /**
     * @var BlogRepository
     */
    protected $blogRepository;

    /**
     * @var CategoryRepository
     */
    protected $categoryRepository;

    /**
     * @var ProductRepository
     */
    protected $productRepository;

    /**
     * @var ConfigRepository
     */
    protected $configRepository;

    /**
     * @var PageRepository
     */
    protected $pageRepository;

    /**
     * @var BrowsedBlogRepository
     */
    protected $browsedBlogRepository;

    /**
     * BlogController constructor.
     *
     * @param BlogRepository $blogRepository
     * @param ProductRepository $productRepository
     * @param BrowsedBlogRepository $browsedBlogRepository
     * @param ConfigRepository $blogRepository
     */
    public function __construct(
        BlogRepository $blogRepository,
        CategoryRepository $categoryRepository,
        PageRepository $pageRepository,
        ProductRepository $productRepository,
        BrowsedBlogRepository $browsedBlogRepository,
        ConfigRepository $configRepository)
    {
        $this->blogRepository = $blogRepository;
        $this->categoryRepository = $categoryRepository;
        $this->productRepository = $productRepository;
        $this->configRepository = $configRepository;
        $this->browsedBlogRepository = $browsedBlogRepository;
        $this->pageRepository = $pageRepository;
    }

    /**
     * @Route("/blog/", name="cm_blog_pro_page_list")
     * @Template("blog/list.twig")
     */
    public function index(Request $request, PaginatorInterface $paginator)
    {
        $form = $this->createForm(BlogType::class);
        $search = $request->query->all();
        $search["status"] = 1;
        $qb = $this->blogRepository->getQueryBuilderBySearchData($search);

        $config = $this->configRepository->get();

        $pagination = $paginator->paginate(
            $qb,
            !empty($search['pageno']) ? $search['pageno'] : 1,
            !empty($search['disp_number']) ? $search['disp_number'] : $config->getDisplayPage()
        );

        $qb = $this->browsedBlogRepository->createQueryBuilder('bb');

        $qb
            ->leftJoin('bb.Blog', 'bbb')
            ->select('bbb.id', 'COUNT(bbb.id) as blogCount')
            ->groupBy('bbb')
            ->orderBy('blogCount', 'DESC')
            ->setMaxResults(2);

        return [
            'form' => $form->createView(),
            'categories' => $this->categoryRepository->findAll(),
            'pagination' => $pagination,
            'monthArr' => $this->setMonthArchive($search),
            'PopularBlogs' => $qb->getQuery()->getResult(),
        ];
    }

    /**
     * @Route("/blog/{id}/", name="cm_blog_pro_page_detail")
     * @Template("blog/detail.twig")
     */
    public function detail(Request $request, $id)
    {
        //postgresql→int の最大値：2147483647だから、最大値を超えるとSlug として判断
        if(is_numeric($id) && $id <= 2147483647) {

            $blog = $this->blogRepository->get($id);

            //IDで検索できない場合、Slugで検索する
            if(!$blog) {

                $blog = $this->blogRepository->findBy(['slug' => $id]);
                $blog = $blog[0];
            }
        } else {

            $blog = $this->blogRepository->findBy(['slug' => $id]);
            $blog = $blog[0];
        }

        if (!$blog || !$this->checkVisibility($blog)) {
            $this->addError('ブログを見つかりませんでした。');
            return $this->redirectToRoute('cm_blog_pro_page_list');
        }

        $config = $this->configRepository->get();

        $form = $this->createForm(BlogType::class, $blog);

        $cmPage = $this->pageRepository->findOneBy(['url'  => 'cm_blog_pro_page_detail']);

        if($blog->getAuthor() != Null){
            $Page["author"] = $blog->getAuthor();
        } else {
            $Page["author"] = $cmPage->getAuthor();
        }
            

        if($blog->getDescription() != Null){
            $Page["description"] = $blog->getDescription();
        } else {
            $Page["description"] = $cmPage->getDescription();
        };
        
        if($blog->getKeyword() != Null){
            $Page["keyword"] = $blog->getKeyword();
        } else {
            $Page["keyword"] = $cmPage->getKeyword();
        };
        

        if($blog->getRobot() != Null){
            $Page["meta_robots"] = $blog->getRobot();
        } else {
            $Page["meta_robots"] = $cmPage->getMetaRobots();
        }
        

        if($blog->getMetatag() != Null){
            $Page["meta_tags"] = $blog->getMetatag();
        } else {
            $Page["meta_tags"] = $cmPage->getMetaTags();
        }

        $tagArr = [];
        if($blog->getTag() != Null){
            $tagArr = preg_split('/[;,　、]+/u', $blog->getTag());
        }
        $Page["edit_type"] = 0;

        $qb = $this->blogRepository->createQueryBuilder('b');
        $qb->select('MIN(b.id) as min_id', 'MAX(b.id) as max_id')
            ->where('b.release_date < :date OR b.release_date is NULL AND b.create_date < :date')
            ->setParameter('date', new \DateTime())
            ->andWhere('b.Status = 1');
            
        $result = $qb->getQuery()->getSingleResult();

        if ($this->isGranted('ROLE_USER')) {
            // 記事閲覧履歴を保存
            $Customer = $this->getUser();

            $qb = $this->browsedBlogRepository->createQueryBuilder('bb');

            $qb
                ->where('bb.Blog = :Blog')
                ->andWhere('bb.Customer = :Customer')
                ->setParameter('Blog', $blog)
                ->setParameter('Customer', $Customer);

            if ( empty($qb->getQuery()->getResult()) ) {
                // 記事閲覧履歴がない場合はDBに保存
                $BrowsedBlog = new BrowsedBlog();
                $BrowsedBlog->setBlog($blog);
                $BrowsedBlog->setCustomer($Customer);

                $this->entityManager->persist($BrowsedBlog);
                $this->entityManager->flush();
            }
        }

        return [
            'form' => $form->createView(),
            'blog' => $blog,
            'Page' => $Page,
            'subtitle' => $blog->getTitle(),
            'tags' => $tagArr,
            'monthArr' => $this->setMonthArchive(),
            'min_id' => $result['min_id'],
            'max_id' => $result['max_id']
        ];
    }

    /**
     * 閲覧可能なブログかどうかを判定
     *
     * @param Blog $blog
     *
     * @return boolean 閲覧可能な場合はtrue
     */
    protected function checkVisibility(Blog $blog)
    {
        $is_admin = $this->session->has('_security_admin');

        // 管理ユーザの場合はステータスやオプションにかかわらず閲覧可能.
        if (!$is_admin) {
            if ($blog->getStatus()->getId() !== BlogStatus::DISPLAY_SHOW) {
                return false;
            }
        }

        return true;
    }

    /**
     * 月別アーカイブ表示のため取得
     *
     * @param 
     *
     * @return array 月別配列
     */
    private function setMonthArchive($search = []) {
        
        $releaseDate = $this->blogRepository->getReleaseDate($search);

        $monthArr = [];
        foreach($releaseDate as $val) {

            if(is_null($val['release_date'])) {
                continue;
            }
            $key = $val['release_date']->format('Y-m');
            if(isset($monthArr[$key])) {
                continue;
            }
            $monthArr[$key] = $val['release_date']->format('Y年m月');
            
        }
        return $monthArr;
    }
}
