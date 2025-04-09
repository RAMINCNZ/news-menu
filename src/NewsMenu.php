<?php

namespace Tywed\Webtrees\Module\NewsMenu;

use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Menu;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\View;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleMenuTrait;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleGlobalTrait;
use Fisharebest\Webtrees\Module\ModuleMenuInterface;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleGlobalInterface;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\MigrationService;
use Fisharebest\Webtrees\Services\HtmlService;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Tywed\Webtrees\Module\NewsMenu\Controllers\NewsController;
use Tywed\Webtrees\Module\NewsMenu\Controllers\CommentController;
use Tywed\Webtrees\Module\NewsMenu\Repositories\NewsRepository;
use Tywed\Webtrees\Module\NewsMenu\Repositories\CommentRepository;
use Tywed\Webtrees\Module\NewsMenu\Services\NewsService;
use Illuminate\Support\Facades\DB;
use Fisharebest\Webtrees\FlashMessages;
use Tywed\Webtrees\Module\NewsMenu\Repositories\CategoryRepository;
use Fisharebest\Webtrees\Validator;
use Fisharebest\Webtrees\Services\DatabaseService;
use Tywed\Webtrees\Module\NewsMenu\Controllers\CategoryController;

/**
 * News Menu Module
 * 
 * A module that provides a news system for Webtrees
 */
class NewsMenu extends AbstractModule implements ModuleCustomInterface, ModuleMenuInterface, ModuleGlobalInterface, ModuleConfigInterface, MiddlewareInterface
{
    use ModuleCustomTrait;
    use ModuleMenuTrait;
    use ModuleGlobalTrait;
    use ModuleConfigTrait;

    public const CUSTOM_MODULE = 'News-Menu';
    public const CUSTOM_AUTHOR = 'Tywed';
    public const CUSTOM_WEBSITE = 'https://github.com/tywed/' . self::CUSTOM_MODULE . '/';
    public const CUSTOM_VERSION = '0.3';
    public const CUSTOM_LAST = self::CUSTOM_WEBSITE . 'raw/main/latest-version.txt';
    public const CUSTOM_SUPPORT_URL = self::CUSTOM_WEBSITE . 'issues';
    public const SCHEMA_VERSION = 2;
    public const SETTING_SCHEMA_NAME = 'NEWS_SCHEMA_VERSION';

    private NewsController $newsController;
    private CommentController $commentController;
    private CategoryController $categoryController;

    /**
     * Constructor for the News Menu module
     */
    public function __construct()
    {
        $htmlService = new HtmlService();
        $newsRepository = new NewsRepository();
        $commentRepository = new CommentRepository();
        $categoryRepository = new CategoryRepository();
        $newsService = new NewsService($newsRepository, $commentRepository, $htmlService);

        $this->newsController = new NewsController($newsService, $commentRepository, $this);
        $this->commentController = new CommentController($commentRepository, $newsService, $this);
        $this->categoryController = new CategoryController($categoryRepository, $this);
    }

    /**
     * {@inheritdoc}
     */
    public function title(): string
    {
        return I18N::translate('News');
    }

    /**
     * {@inheritdoc}
     */
    public function description(): string
    {
        return I18N::translate('Add an extra item to the main menu as a link to a webtrees news.');
    }

    /**
     * {@inheritdoc}
     */
    public function customModuleAuthorName(): string
    {
        return self::CUSTOM_AUTHOR;
    }

    /**
     * {@inheritdoc}
     */
    public function customModuleVersion(): string
    {
        return self::CUSTOM_VERSION;
    }

    /**
     * {@inheritdoc}
     */
    public function customModuleLatestVersionUrl(): string
    {
        return self::CUSTOM_LAST;
    }

    /**
     * {@inheritdoc}
     */
    public function customModuleSupportUrl(): string
    {
        return self::CUSTOM_SUPPORT_URL;
    }

    /**
     * Bootstrap the module
     */
    public function boot(): void
    {
        View::registerNamespace($this->name(), $this->resourcesFolder() . 'views/');

        // Run migrations
        $migrations_namespace = __NAMESPACE__ . '\Migrations';
        
        Registry::container()->get(MigrationService::class)->updateSchema(
            $migrations_namespace,
            self::SETTING_SCHEMA_NAME, 
            self::SCHEMA_VERSION
        );
    }

    /**
     * HTTP request processing
     * 
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($request);
    }

    /**
     * Path to module resources
     * 
     * @return string
     */
    public function resourcesFolder(): string
    {
        return dirname(__DIR__) . '/resources/';
    }

    /**
     * Load translations
     * 
     * @param string $language
     * @return array
     */
    public function customTranslations(string $language): array
    {
        $file = $this->resourcesFolder() . "langs/{$language}.php";

        return file_exists($file)
            ? require $file
            : require $this->resourcesFolder() . 'langs/en.php';
    }

    /**
     * Get the default menu order
     * 
     * @return int
     */
    public function defaultMenuOrder(): int
    {
        return (int)$this->getPreference('news_menu_order', '-1');
    }

    /**
     * Get the menu for this module
     * 
     * @param Tree $tree
     * @return Menu|null
     */
    public function getMenu(Tree $tree): ?Menu
    {
        if ($tree === null) {
            return null;
        }

        $url = route('module', [
            'tree' => $tree->name(),
            'module' => $this->name(),
            'action' => 'Page',
        ]);

        $menu_title = I18N::translate('News');

        return new Menu($menu_title, e($url), 'news-menu');
    }

    /**
     * Add CSS files
     * 
     * @return string
     */
    public function headContent(): string
    {
        $url = $this->assetUrl('css/news-menu.css');

        return '<link rel="stylesheet" href="' . e($url) . '">';
    }

    /**
     * Display the news page
     * 
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function getPageAction(ServerRequestInterface $request): ResponseInterface
    {
        return $this->newsController->page($request);
    }

    /**
     * Show a single news entry
     * 
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function getShowNewsAction(ServerRequestInterface $request): ResponseInterface
    {
        return $this->newsController->show($request);
    }

    /**
     * Edit news form
     * 
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function getEditNewsAction(ServerRequestInterface $request): ResponseInterface
    {
        return $this->newsController->edit($request);
    }

    /**
     * Update a news entry
     * 
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function postEditNewsAction(ServerRequestInterface $request): ResponseInterface
    {
        return $this->newsController->update($request);
    }

    /**
     * Delete a news entry
     * 
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function postDeleteNewsAction(ServerRequestInterface $request): ResponseInterface
    {
        return $this->newsController->delete($request);
    }

    /**
     * Like a news entry
     * 
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function getLikeNewsAction(ServerRequestInterface $request): ResponseInterface
    {
        return $this->newsController->like($request);
    }

    /**
     * Create a comment
     * 
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function postShowNewsAction(ServerRequestInterface $request): ResponseInterface
    {
        return $this->commentController->create($request);
    }

    /**
     * Delete a comment
     * 
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function postDeleteCommentsAction(ServerRequestInterface $request): ResponseInterface
    {
        return $this->commentController->delete($request);
    }

    /**
     * Like a comment
     * 
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function getLikeCommentsAction(ServerRequestInterface $request): ResponseInterface
    {
        return $this->commentController->like($request);
    }

    /**
     * Show news filtered by category
     * 
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function getCategoryAction(ServerRequestInterface $request): ResponseInterface
    {
        return $this->newsController->category($request);
    }

    /**
     * Toggle pinned status of a news article
     * 
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function getTogglePinnedAction(ServerRequestInterface $request): ResponseInterface
    {
        return $this->newsController->togglePinned($request);
    }

    /**
     * Admin settings page
     * 
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function getAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->layout = 'layouts/administration';

        $categoryRepository = new CategoryRepository();
        $categories = $categoryRepository->findAll();

        return $this->viewResponse($this->name() . '::settings', [
            'title' => $this->title(),
            'news_menu_order' => $this->getPreference('news_menu_order', '-1'),
            'limit_news' => $this->getPreference('limit_news', '5'),
            'limit_comments' => $this->getPreference('limit_comments', '5'),
            'min_views_popular' => $this->getPreference('min_views_popular', '5'),
            'categories' => $categories,
            'module_name' => $this->name(),
        ]);
    }

    /**
     * Save admin settings
     * 
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function postAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $params = (array) $request->getParsedBody();

        $this->setPreference('news_menu_order', $params['news_menu_order']);
        $this->setPreference('limit_news', $params['limit_news']);
        $this->setPreference('limit_comments', $params['limit_comments']);
        $this->setPreference('min_views_popular', $params['min_views_popular']);

        $message = I18N::translate('The preferences for the module " %s " have been updated.', $this->title());
        FlashMessages::addMessage($message, 'success');

        return redirect($this->getConfigLink());
    }

    /**
     * Add a new category
     * 
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function postAddCategoryAction(ServerRequestInterface $request): ResponseInterface
    {
        return $this->categoryController->add($request);
    }

    /**
     * Edit category form
     * 
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function getEditCategoryAction(ServerRequestInterface $request): ResponseInterface
    {
        return $this->categoryController->edit($request);
    }
    
    /**
     * Update category
     * 
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function postEditCategoryAction(ServerRequestInterface $request): ResponseInterface
    {
        return $this->categoryController->update($request);
    }

    /**
     * Delete a category
     * 
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function postDeleteCategoryAction(ServerRequestInterface $request): ResponseInterface
    {
        return $this->categoryController->delete($request);
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigLink(): string
    {
        return route('module', [
            'module' => $this->name(),
            'action' => 'Admin',
        ]);
    }
} 