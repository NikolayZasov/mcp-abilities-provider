<?php
/**
 * Plugin Name:       MCP Abilities Provider
 * Plugin URI:        https://github.com/zasovskiy/mcp-abilities-provider
 * Description:       Универсальный провайдер abilities для WordPress MCP Adapter. Открывает core abilities и регистрирует abilities для управления контентом (посты, страницы, медиа, категории, меню, пользователи, настройки). Работает на любом WordPress 6.9+ сайте.
 * Version:           1.3.0
 * Requires at least: 6.9
 * Requires PHP:      8.1
 * Author:            Zasovskiy
 * Author URI:        https://zasovskiy.ru
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mcp-abilities-provider
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MCPAP_VERSION', '1.3.0' );
define( 'MCPAP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MCPAP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Проверка наличия Abilities API.
 *
 * @since 1.0.0
 */
function mcpap_check_dependencies(): bool {
	return function_exists( 'wp_register_ability' );
}

/**
 * Уведомление об отсутствии зависимостей.
 *
 * @since 1.0.0
 */
function mcpap_admin_notice_missing_deps(): void {
	?>
	<div class="notice notice-error">
		<p>
			<strong>MCP Abilities Provider:</strong>
			<?php esc_html_e( 'Для работы плагина требуется WordPress 6.9+ с Abilities API и установленный MCP Adapter.', 'mcp-abilities-provider' ); ?>
		</p>
	</div>
	<?php
}

if ( ! mcpap_check_dependencies() ) {
	add_action( 'admin_notices', 'mcpap_admin_notice_missing_deps' );
	return;
}

// ─────────────────────────────────────────────────────────────────────────────
// 1. Открываем core abilities для MCP (делаем их публичными)
// ─────────────────────────────────────────────────────────────────────────────

add_filter( 'wp_register_ability_args', 'mcpap_expose_core_abilities', 10, 2 );

/**
 * Помечает core abilities как публичные для MCP.
 *
 * @since 1.0.0
 *
 * @param array  $args       Аргументы регистрации ability.
 * @param string $ability_id Идентификатор ability.
 * @return array Модифицированные аргументы.
 */
function mcpap_expose_core_abilities( array $args, string $ability_id ): array {
	$core_abilities = [
		'core/get-site-info',
		'core/get-user-info',
		'core/get-environment-info',
	];

	if ( in_array( $ability_id, $core_abilities, true ) ) {
		if ( ! isset( $args['meta'] ) ) {
			$args['meta'] = [];
		}
		$args['meta']['mcp'] = [
			'public' => true,
			'type'   => 'tool',
		];
	}

	return $args;
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. Регистрация категорий abilities
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'wp_abilities_api_categories_init', 'mcpap_register_categories' );

/**
 * Регистрирует категории для наших abilities.
 *
 * @since 1.0.0
 */
function mcpap_register_categories(): void {
	$categories = [
		'content'  => [
			'label'       => __( 'Контент', 'mcp-abilities-provider' ),
			'description' => __( 'Abilities для управления контентом сайта: посты, страницы, медиа.', 'mcp-abilities-provider' ),
		],
		'taxonomy' => [
			'label'       => __( 'Таксономии', 'mcp-abilities-provider' ),
			'description' => __( 'Abilities для управления категориями и метками.', 'mcp-abilities-provider' ),
		],
		'settings' => [
			'label'       => __( 'Настройки', 'mcp-abilities-provider' ),
			'description' => __( 'Abilities для чтения и изменения настроек сайта.', 'mcp-abilities-provider' ),
		],
		'classified' => [
			'label'       => __( 'Доска объявлений', 'mcp-abilities-provider' ),
			'description' => __( 'Abilities для управления объявлениями, категориями, локациями и магазинами Classified Listing.', 'mcp-abilities-provider' ),
		],
	];

	foreach ( $categories as $slug => $args ) {
		if ( ! wp_has_ability_category( $slug ) ) {
			wp_register_ability_category( $slug, $args );
		}
	}
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. Регистрация abilities для управления контентом
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'wp_abilities_api_init', 'mcpap_register_abilities' );

/**
 * Регистрирует все abilities плагина.
 *
 * @since 1.0.0
 */
function mcpap_register_abilities(): void {
	mcpap_register_post_abilities();
	mcpap_register_page_abilities();
	mcpap_register_media_abilities();
	mcpap_register_taxonomy_abilities();
	mcpap_register_user_abilities();
	mcpap_register_settings_abilities();
	mcpap_register_menu_abilities();
	mcpap_register_plugin_abilities();
	mcpap_register_comment_abilities();

	// WooCommerce abilities (только если WooCommerce активен).
	if ( class_exists( 'WooCommerce' ) ) {
		mcpap_register_wc_product_abilities();
		mcpap_register_wc_product_taxonomy_abilities();
		mcpap_register_wc_order_abilities();
		mcpap_register_wc_coupon_abilities();
	}

	// Classified Listing abilities (только если Classified Listing активен).
	if ( defined( 'RTCL_VERSION' ) ) {
		mcpap_register_rtcl_listing_abilities();
		mcpap_register_rtcl_taxonomy_abilities();
		mcpap_register_rtcl_config_abilities();

		// Store & Membership Addon.
		if ( class_exists( 'RtclStore' ) ) {
			mcpap_register_rtcl_store_abilities();
		}
	}
}

// ═════════════════════════════════════════════════════════════════════════════
// ПОСТЫ
// ═════════════════════════════════════════════════════════════════════════════

/**
 * Регистрирует abilities для работы с постами.
 *
 * @since 1.0.0
 */
function mcpap_register_post_abilities(): void {

	// --- Получить список постов ---
	wp_register_ability( 'mcpap/get-posts', [
		'label'       => __( 'Получить посты', 'mcp-abilities-provider' ),
		'description' => __( 'Получить список постов с фильтрацией по статусу, категории, автору, количеству и поиску.', 'mcp-abilities-provider' ),
		'category'    => 'content',
		'readonly'    => true,
		'meta'        => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		'input_schema' => [
			'type'       => 'object',
			'properties' => [
				'numberposts' => [
					'type'        => 'integer',
					'description' => 'Количество постов (по умолчанию 10, максимум 100)',
					'default'     => 10,
					'minimum'     => 1,
					'maximum'     => 100,
				],
				'post_status' => [
					'type'        => 'string',
					'description' => 'Статус поста: publish, draft, pending, private, trash',
					'enum'        => [ 'publish', 'draft', 'pending', 'private', 'trash', 'any' ],
					'default'     => 'publish',
				],
				'category'    => [
					'type'        => 'integer',
					'description' => 'ID категории для фильтрации',
				],
				'search'      => [
					'type'        => 'string',
					'description' => 'Поисковый запрос',
				],
				'orderby'     => [
					'type'        => 'string',
					'description' => 'Сортировка: date, title, modified, rand',
					'enum'        => [ 'date', 'title', 'modified', 'rand', 'ID' ],
					'default'     => 'date',
				],
				'order'       => [
					'type'        => 'string',
					'description' => 'Направление: ASC или DESC',
					'enum'        => [ 'ASC', 'DESC' ],
					'default'     => 'DESC',
				],
				'offset'      => [
					'type'        => 'integer',
					'description' => 'Смещение для пагинации',
					'default'     => 0,
				],
			],
		],
		'execute_callback'    => function ( array $input ): array {
			$args = [
				'numberposts' => min( absint( $input['numberposts'] ?? 10 ), 100 ),
				'post_status' => sanitize_text_field( $input['post_status'] ?? 'publish' ),
				'orderby'     => sanitize_text_field( $input['orderby'] ?? 'date' ),
				'order'       => sanitize_text_field( $input['order'] ?? 'DESC' ),
			];

			if ( ! empty( $input['category'] ) ) {
				$args['cat'] = absint( $input['category'] );
			}
			if ( ! empty( $input['search'] ) ) {
				$args['s'] = sanitize_text_field( $input['search'] );
			}
			if ( ! empty( $input['offset'] ) ) {
				$args['offset'] = absint( $input['offset'] );
			}

			$posts  = get_posts( $args );
			$result = [];

			foreach ( $posts as $post ) {
				$result[] = mcpap_format_post( $post );
			}

			return $result;
		},
		'permission_callback' => function (): bool {
			return current_user_can( 'edit_posts' );
		},
	] );

	// --- Получить один пост ---
	wp_register_ability( 'mcpap/get-post', [
		'label'       => __( 'Получить пост по ID', 'mcp-abilities-provider' ),
		'description' => __( 'Возвращает полные данные поста по его ID, включая контент, метаданные и SEO-поля.', 'mcp-abilities-provider' ),
		'category'    => 'content',
		'readonly'    => true,
		'meta'        => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		'input_schema' => [
			'type'       => 'object',
			'required'   => [ 'post_id' ],
			'properties' => [
				'post_id' => [
					'type'        => 'integer',
					'description' => 'ID поста',
				],
			],
		],
		'execute_callback'    => function ( array $input ): array|WP_Error {
			$post = get_post( absint( $input['post_id'] ) );
			if ( ! $post || 'post' !== $post->post_type ) {
				return new WP_Error( 'not_found', 'Пост не найден' );
			}
			return mcpap_format_post( $post, true );
		},
		'permission_callback' => function (): bool {
			return current_user_can( 'edit_posts' );
		},
	] );

	// --- Создать пост ---
	wp_register_ability( 'mcpap/create-post', [
		'label'       => __( 'Создать пост', 'mcp-abilities-provider' ),
		'description' => __( 'Создаёт новый пост с заголовком, контентом, статусом, категориями и метками.', 'mcp-abilities-provider' ),
		'category'    => 'content',
		'meta'        => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		'input_schema' => [
			'type'       => 'object',
			'required'   => [ 'title', 'content' ],
			'properties' => [
				'title'      => [
					'type'        => 'string',
					'description' => 'Заголовок поста',
				],
				'content'    => [
					'type'        => 'string',
					'description' => 'Содержимое поста (HTML или текст)',
				],
				'status'     => [
					'type'        => 'string',
					'description' => 'Статус: draft, publish, pending, private',
					'enum'        => [ 'draft', 'publish', 'pending', 'private' ],
					'default'     => 'draft',
				],
				'categories' => [
					'type'        => 'array',
					'description' => 'Массив ID категорий',
					'items'       => [ 'type' => 'integer' ],
				],
				'tags'       => [
					'type'        => 'array',
					'description' => 'Массив названий меток',
					'items'       => [ 'type' => 'string' ],
				],
				'excerpt'    => [
					'type'        => 'string',
					'description' => 'Краткое описание поста',
				],
				'slug'       => [
					'type'        => 'string',
					'description' => 'URL-slug поста',
				],
				'featured_image_id' => [
					'type'        => 'integer',
					'description' => 'ID изображения из медиабиблиотеки для миниатюры',
				],
			],
		],
		'execute_callback'    => function ( array $input ): array|WP_Error {
			$post_data = [
				'post_title'   => sanitize_text_field( $input['title'] ),
				'post_content' => wp_kses_post( $input['content'] ),
				'post_status'  => sanitize_text_field( $input['status'] ?? 'draft' ),
				'post_type'    => 'post',
			];

			if ( ! empty( $input['excerpt'] ) ) {
				$post_data['post_excerpt'] = sanitize_text_field( $input['excerpt'] );
			}
			if ( ! empty( $input['slug'] ) ) {
				$post_data['post_name'] = sanitize_title( $input['slug'] );
			}
			if ( ! empty( $input['categories'] ) ) {
				$post_data['post_category'] = array_map( 'absint', $input['categories'] );
			}
			if ( ! empty( $input['tags'] ) ) {
				$post_data['tags_input'] = array_map( 'sanitize_text_field', $input['tags'] );
			}

			$post_id = wp_insert_post( $post_data, true );

			if ( is_wp_error( $post_id ) ) {
				return $post_id;
			}

			if ( ! empty( $input['featured_image_id'] ) ) {
				set_post_thumbnail( $post_id, absint( $input['featured_image_id'] ) );
			}

			return [
				'post_id' => $post_id,
				'url'     => get_permalink( $post_id ),
				'edit_url' => get_edit_post_link( $post_id, 'raw' ),
				'status'  => get_post_status( $post_id ),
			];
		},
		'permission_callback' => function (): bool {
			return current_user_can( 'publish_posts' );
		},
	] );

	// --- Обновить пост ---
	wp_register_ability( 'mcpap/update-post', [
		'label'       => __( 'Обновить пост', 'mcp-abilities-provider' ),
		'description' => __( 'Обновляет существующий пост: заголовок, контент, статус, категории, метки, slug, миниатюру.', 'mcp-abilities-provider' ),
		'category'    => 'content',
		'meta'        => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		'input_schema' => [
			'type'       => 'object',
			'required'   => [ 'post_id' ],
			'properties' => [
				'post_id'    => [
					'type'        => 'integer',
					'description' => 'ID поста для обновления',
				],
				'title'      => [
					'type'        => 'string',
					'description' => 'Новый заголовок',
				],
				'content'    => [
					'type'        => 'string',
					'description' => 'Новый контент',
				],
				'status'     => [
					'type'        => 'string',
					'description' => 'Новый статус',
					'enum'        => [ 'draft', 'publish', 'pending', 'private' ],
				],
				'categories' => [
					'type'        => 'array',
					'description' => 'Новые ID категорий',
					'items'       => [ 'type' => 'integer' ],
				],
				'tags'       => [
					'type'        => 'array',
					'description' => 'Новые метки',
					'items'       => [ 'type' => 'string' ],
				],
				'excerpt'    => [
					'type'        => 'string',
					'description' => 'Новое краткое описание',
				],
				'slug'       => [
					'type'        => 'string',
					'description' => 'Новый URL-slug',
				],
				'featured_image_id' => [
					'type'        => 'integer',
					'description' => 'ID нового изображения для миниатюры (0 — убрать)',
				],
			],
		],
		'execute_callback'    => function ( array $input ): array|WP_Error {
			$post_id = absint( $input['post_id'] );
			$post    = get_post( $post_id );

			if ( ! $post || 'post' !== $post->post_type ) {
				return new WP_Error( 'not_found', 'Пост не найден' );
			}

			$post_data = [ 'ID' => $post_id ];

			if ( isset( $input['title'] ) ) {
				$post_data['post_title'] = sanitize_text_field( $input['title'] );
			}
			if ( isset( $input['content'] ) ) {
				$post_data['post_content'] = wp_kses_post( $input['content'] );
			}
			if ( isset( $input['status'] ) ) {
				$post_data['post_status'] = sanitize_text_field( $input['status'] );
			}
			if ( isset( $input['excerpt'] ) ) {
				$post_data['post_excerpt'] = sanitize_text_field( $input['excerpt'] );
			}
			if ( isset( $input['slug'] ) ) {
				$post_data['post_name'] = sanitize_title( $input['slug'] );
			}
			if ( isset( $input['categories'] ) ) {
				$post_data['post_category'] = array_map( 'absint', $input['categories'] );
			}
			if ( isset( $input['tags'] ) ) {
				$post_data['tags_input'] = array_map( 'sanitize_text_field', $input['tags'] );
			}

			$result = wp_update_post( $post_data, true );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			if ( isset( $input['featured_image_id'] ) ) {
				$img_id = absint( $input['featured_image_id'] );
				if ( 0 === $img_id ) {
					delete_post_thumbnail( $post_id );
				} else {
					set_post_thumbnail( $post_id, $img_id );
				}
			}

			return [
				'post_id' => $post_id,
				'url'     => get_permalink( $post_id ),
				'updated' => true,
			];
		},
		'permission_callback' => function (): bool {
			return current_user_can( 'edit_others_posts' );
		},
	] );

	// --- Удалить пост ---
	wp_register_ability( 'mcpap/delete-post', [
		'label'       => __( 'Удалить пост', 'mcp-abilities-provider' ),
		'description' => __( 'Перемещает пост в корзину или удаляет окончательно.', 'mcp-abilities-provider' ),
		'category'    => 'content',
		'meta'        => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		'input_schema' => [
			'type'       => 'object',
			'required'   => [ 'post_id' ],
			'properties' => [
				'post_id'     => [
					'type'        => 'integer',
					'description' => 'ID поста для удаления',
				],
				'force_delete' => [
					'type'        => 'boolean',
					'description' => 'true — удалить окончательно, false — в корзину',
					'default'     => false,
				],
			],
		],
		'execute_callback'    => function ( array $input ): array|WP_Error {
			$post_id = absint( $input['post_id'] );
			$post    = get_post( $post_id );

			if ( ! $post ) {
				return new WP_Error( 'not_found', 'Пост не найден' );
			}

			$force  = (bool) ( $input['force_delete'] ?? false );
			$result = wp_delete_post( $post_id, $force );

			if ( ! $result ) {
				return new WP_Error( 'delete_failed', 'Не удалось удалить пост' );
			}

			return [
				'post_id' => $post_id,
				'deleted' => true,
				'trashed' => ! $force,
			];
		},
		'permission_callback' => function (): bool {
			return current_user_can( 'delete_others_posts' );
		},
	] );
}

// ═════════════════════════════════════════════════════════════════════════════
// СТРАНИЦЫ
// ═════════════════════════════════════════════════════════════════════════════

/**
 * Регистрирует abilities для работы со страницами.
 *
 * @since 1.0.0
 */
function mcpap_register_page_abilities(): void {

	wp_register_ability( 'mcpap/get-pages', [
		'label'       => __( 'Получить страницы', 'mcp-abilities-provider' ),
		'description' => __( 'Список всех страниц сайта с иерархией.', 'mcp-abilities-provider' ),
		'category'    => 'content',
		'readonly'    => true,
		'meta'        => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		'input_schema' => [
			'type'       => 'object',
			'properties' => [
				'numberposts' => [
					'type'    => 'integer',
					'default' => 50,
					'maximum' => 100,
				],
				'post_status' => [
					'type'    => 'string',
					'default' => 'publish',
					'enum'    => [ 'publish', 'draft', 'pending', 'private', 'any' ],
				],
				'parent'      => [
					'type'        => 'integer',
					'description' => 'ID родительской страницы (0 — только верхний уровень)',
				],
				'search'      => [
					'type'        => 'string',
					'description' => 'Поисковый запрос',
				],
			],
		],
		'execute_callback'    => function ( array $input ): array {
			$args = [
				'post_type'   => 'page',
				'numberposts' => min( absint( $input['numberposts'] ?? 50 ), 100 ),
				'post_status' => sanitize_text_field( $input['post_status'] ?? 'publish' ),
				'orderby'     => 'menu_order',
				'order'       => 'ASC',
			];

			if ( isset( $input['parent'] ) ) {
				$args['post_parent'] = absint( $input['parent'] );
			}
			if ( ! empty( $input['search'] ) ) {
				$args['s'] = sanitize_text_field( $input['search'] );
			}

			$pages  = get_posts( $args );
			$result = [];

			foreach ( $pages as $page ) {
				$result[] = [
					'ID'          => $page->ID,
					'title'       => $page->post_title,
					'slug'        => $page->post_name,
					'status'      => $page->post_status,
					'parent_id'   => $page->post_parent,
					'menu_order'  => $page->menu_order,
					'url'         => get_permalink( $page ),
					'template'    => get_page_template_slug( $page ),
					'modified'    => $page->post_modified,
				];
			}

			return $result;
		},
		'permission_callback' => function (): bool {
			return current_user_can( 'edit_pages' );
		},
	] );

	wp_register_ability( 'mcpap/get-page', [
		'label'       => __( 'Получить страницу по ID', 'mcp-abilities-provider' ),
		'description' => __( 'Полные данные страницы по ID.', 'mcp-abilities-provider' ),
		'category'    => 'content',
		'readonly'    => true,
		'meta'        => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		'input_schema' => [
			'type'       => 'object',
			'required'   => [ 'page_id' ],
			'properties' => [
				'page_id' => [
					'type'        => 'integer',
					'description' => 'ID страницы',
				],
			],
		],
		'execute_callback'    => function ( array $input ): array|WP_Error {
			$page = get_post( absint( $input['page_id'] ) );
			if ( ! $page || 'page' !== $page->post_type ) {
				return new WP_Error( 'not_found', 'Страница не найдена' );
			}
			return [
				'ID'          => $page->ID,
				'title'       => $page->post_title,
				'content'     => $page->post_content,
				'excerpt'     => $page->post_excerpt,
				'slug'        => $page->post_name,
				'status'      => $page->post_status,
				'parent_id'   => $page->post_parent,
				'template'    => get_page_template_slug( $page ),
				'url'         => get_permalink( $page ),
				'modified'    => $page->post_modified,
				'author'      => get_the_author_meta( 'display_name', $page->post_author ),
			];
		},
		'permission_callback' => function (): bool {
			return current_user_can( 'edit_pages' );
		},
	] );

	wp_register_ability( 'mcpap/create-page', [
		'label'       => __( 'Создать страницу', 'mcp-abilities-provider' ),
		'description' => __( 'Создаёт новую страницу.', 'mcp-abilities-provider' ),
		'category'    => 'content',
		'meta'        => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		'input_schema' => [
			'type'       => 'object',
			'required'   => [ 'title' ],
			'properties' => [
				'title'    => [ 'type' => 'string', 'description' => 'Заголовок страницы' ],
				'content'  => [ 'type' => 'string', 'description' => 'Содержимое страницы' ],
				'status'   => [ 'type' => 'string', 'default' => 'draft', 'enum' => [ 'draft', 'publish', 'pending', 'private' ] ],
				'parent'   => [ 'type' => 'integer', 'description' => 'ID родительской страницы' ],
				'template' => [ 'type' => 'string', 'description' => 'Шаблон страницы' ],
				'slug'     => [ 'type' => 'string', 'description' => 'URL-slug' ],
				'order'    => [ 'type' => 'integer', 'description' => 'Порядок в меню' ],
			],
		],
		'execute_callback'    => function ( array $input ): array|WP_Error {
			$post_data = [
				'post_title'  => sanitize_text_field( $input['title'] ),
				'post_type'   => 'page',
				'post_status' => sanitize_text_field( $input['status'] ?? 'draft' ),
			];

			if ( isset( $input['content'] ) ) {
				$post_data['post_content'] = wp_kses_post( $input['content'] );
			}
			if ( isset( $input['parent'] ) ) {
				$post_data['post_parent'] = absint( $input['parent'] );
			}
			if ( isset( $input['slug'] ) ) {
				$post_data['post_name'] = sanitize_title( $input['slug'] );
			}
			if ( isset( $input['order'] ) ) {
				$post_data['menu_order'] = absint( $input['order'] );
			}

			$page_id = wp_insert_post( $post_data, true );

			if ( is_wp_error( $page_id ) ) {
				return $page_id;
			}

			if ( ! empty( $input['template'] ) ) {
				update_post_meta( $page_id, '_wp_page_template', sanitize_text_field( $input['template'] ) );
			}

			return [
				'page_id'  => $page_id,
				'url'      => get_permalink( $page_id ),
				'edit_url' => get_edit_post_link( $page_id, 'raw' ),
			];
		},
		'permission_callback' => function (): bool {
			return current_user_can( 'publish_pages' );
		},
	] );

	wp_register_ability( 'mcpap/update-page', [
		'label'       => __( 'Обновить страницу', 'mcp-abilities-provider' ),
		'description' => __( 'Обновляет существующую страницу.', 'mcp-abilities-provider' ),
		'category'    => 'content',
		'meta'        => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		'input_schema' => [
			'type'       => 'object',
			'required'   => [ 'page_id' ],
			'properties' => [
				'page_id'  => [ 'type' => 'integer', 'description' => 'ID страницы' ],
				'title'    => [ 'type' => 'string', 'description' => 'Новый заголовок' ],
				'content'  => [ 'type' => 'string', 'description' => 'Новый контент' ],
				'status'   => [ 'type' => 'string', 'enum' => [ 'draft', 'publish', 'pending', 'private' ] ],
				'parent'   => [ 'type' => 'integer', 'description' => 'Новый родитель' ],
				'template' => [ 'type' => 'string', 'description' => 'Новый шаблон' ],
				'slug'     => [ 'type' => 'string', 'description' => 'Новый slug' ],
				'order'    => [ 'type' => 'integer', 'description' => 'Новый порядок' ],
			],
		],
		'execute_callback'    => function ( array $input ): array|WP_Error {
			$page_id = absint( $input['page_id'] );
			$page    = get_post( $page_id );

			if ( ! $page || 'page' !== $page->post_type ) {
				return new WP_Error( 'not_found', 'Страница не найдена' );
			}

			$post_data = [ 'ID' => $page_id ];

			if ( isset( $input['title'] ) )   $post_data['post_title']   = sanitize_text_field( $input['title'] );
			if ( isset( $input['content'] ) ) $post_data['post_content'] = wp_kses_post( $input['content'] );
			if ( isset( $input['status'] ) )  $post_data['post_status']  = sanitize_text_field( $input['status'] );
			if ( isset( $input['parent'] ) )  $post_data['post_parent']  = absint( $input['parent'] );
			if ( isset( $input['slug'] ) )    $post_data['post_name']    = sanitize_title( $input['slug'] );
			if ( isset( $input['order'] ) )   $post_data['menu_order']   = absint( $input['order'] );

			$result = wp_update_post( $post_data, true );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			if ( isset( $input['template'] ) ) {
				update_post_meta( $page_id, '_wp_page_template', sanitize_text_field( $input['template'] ) );
			}

			return [ 'page_id' => $page_id, 'url' => get_permalink( $page_id ), 'updated' => true ];
		},
		'permission_callback' => function (): bool {
			return current_user_can( 'edit_others_pages' );
		},
	] );

	wp_register_ability( 'mcpap/delete-page', [
		'label'       => __( 'Удалить страницу', 'mcp-abilities-provider' ),
		'description' => __( 'Перемещает страницу в корзину или удаляет.', 'mcp-abilities-provider' ),
		'category'    => 'content',
		'meta'        => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		'input_schema' => [
			'type'       => 'object',
			'required'   => [ 'page_id' ],
			'properties' => [
				'page_id'      => [ 'type' => 'integer', 'description' => 'ID страницы' ],
				'force_delete' => [ 'type' => 'boolean', 'default' => false ],
			],
		],
		'execute_callback'    => function ( array $input ): array|WP_Error {
			$page = get_post( absint( $input['page_id'] ) );
			if ( ! $page || 'page' !== $page->post_type ) {
				return new WP_Error( 'not_found', 'Страница не найдена' );
			}
			$result = wp_delete_post( $page->ID, (bool) ( $input['force_delete'] ?? false ) );
			if ( ! $result ) {
				return new WP_Error( 'delete_failed', 'Ошибка удаления' );
			}
			return [ 'page_id' => $page->ID, 'deleted' => true ];
		},
		'permission_callback' => function (): bool {
			return current_user_can( 'delete_others_pages' );
		},
	] );
}

// ═════════════════════════════════════════════════════════════════════════════
// МЕДИА
// ═════════════════════════════════════════════════════════════════════════════

/**
 * Регистрирует abilities для работы с медиафайлами.
 *
 * @since 1.0.0
 */
function mcpap_register_media_abilities(): void {

	wp_register_ability( 'mcpap/get-media', [
		'label'       => __( 'Получить медиафайлы', 'mcp-abilities-provider' ),
		'description' => __( 'Список медиафайлов с фильтрацией по типу и поиском.', 'mcp-abilities-provider' ),
		'category'    => 'content',
		'readonly'    => true,
		'meta'        => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		'input_schema' => [
			'type'       => 'object',
			'properties' => [
				'numberposts' => [ 'type' => 'integer', 'default' => 20, 'maximum' => 100 ],
				'mime_type'   => [
					'type'        => 'string',
					'description' => 'MIME-тип: image, video, audio, application, или конкретный (image/jpeg)',
				],
				'search'      => [ 'type' => 'string', 'description' => 'Поиск по имени файла' ],
			],
		],
		'execute_callback'    => function ( array $input ): array {
			$args = [
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
				'numberposts' => min( absint( $input['numberposts'] ?? 20 ), 100 ),
			];

			if ( ! empty( $input['mime_type'] ) ) {
				$args['post_mime_type'] = sanitize_text_field( $input['mime_type'] );
			}
			if ( ! empty( $input['search'] ) ) {
				$args['s'] = sanitize_text_field( $input['search'] );
			}

			$media  = get_posts( $args );
			$result = [];

			foreach ( $media as $item ) {
				$result[] = [
					'ID'        => $item->ID,
					'title'     => $item->post_title,
					'url'       => wp_get_attachment_url( $item->ID ),
					'mime_type' => $item->post_mime_type,
					'alt_text'  => get_post_meta( $item->ID, '_wp_attachment_image_alt', true ),
					'caption'   => $item->post_excerpt,
					'date'      => $item->post_date,
					'filesize'  => filesize( get_attached_file( $item->ID ) ) ?: null,
				];
			}

			return $result;
		},
		'permission_callback' => function (): bool {
			return current_user_can( 'upload_files' );
		},
	] );

	wp_register_ability( 'mcpap/update-media', [
		'label'       => __( 'Обновить медиафайл', 'mcp-abilities-provider' ),
		'description' => __( 'Обновляет заголовок, alt-текст и описание медиафайла.', 'mcp-abilities-provider' ),
		'category'    => 'content',
		'meta'        => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		'input_schema' => [
			'type'       => 'object',
			'required'   => [ 'media_id' ],
			'properties' => [
				'media_id' => [ 'type' => 'integer', 'description' => 'ID медиафайла' ],
				'title'    => [ 'type' => 'string', 'description' => 'Новый заголовок' ],
				'alt_text' => [ 'type' => 'string', 'description' => 'Новый alt-текст' ],
				'caption'  => [ 'type' => 'string', 'description' => 'Новая подпись' ],
				'description' => [ 'type' => 'string', 'description' => 'Новое описание' ],
			],
		],
		'execute_callback'    => function ( array $input ): array|WP_Error {
			$media_id = absint( $input['media_id'] );
			$media    = get_post( $media_id );

			if ( ! $media || 'attachment' !== $media->post_type ) {
				return new WP_Error( 'not_found', 'Медиафайл не найден' );
			}

			$post_data = [ 'ID' => $media_id ];
			if ( isset( $input['title'] ) )       $post_data['post_title']   = sanitize_text_field( $input['title'] );
			if ( isset( $input['caption'] ) )     $post_data['post_excerpt'] = sanitize_text_field( $input['caption'] );
			if ( isset( $input['description'] ) ) $post_data['post_content'] = wp_kses_post( $input['description'] );

			wp_update_post( $post_data );

			if ( isset( $input['alt_text'] ) ) {
				update_post_meta( $media_id, '_wp_attachment_image_alt', sanitize_text_field( $input['alt_text'] ) );
			}

			return [ 'media_id' => $media_id, 'updated' => true ];
		},
		'permission_callback' => function (): bool {
			return current_user_can( 'upload_files' );
		},
	] );
}

// ═════════════════════════════════════════════════════════════════════════════
// ТАКСОНОМИИ (категории и метки)
// ═════════════════════════════════════════════════════════════════════════════

/**
 * Регистрирует abilities для таксономий.
 *
 * @since 1.0.0
 */
function mcpap_register_taxonomy_abilities(): void {

	wp_register_ability( 'mcpap/get-categories', [
		'label'       => __( 'Получить категории', 'mcp-abilities-provider' ),
		'description' => __( 'Возвращает все категории сайта.', 'mcp-abilities-provider' ),
		'category'    => 'taxonomy',
		'readonly'    => true,
		'meta'        => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		'input_schema' => [
			'type'       => 'object',
			'properties' => [
				'hide_empty' => [ 'type' => 'boolean', 'default' => false, 'description' => 'Скрыть пустые категории' ],
				'parent'     => [ 'type' => 'integer', 'description' => 'ID родительской категории' ],
			],
		],
		'execute_callback'    => function ( array $input ): array {
			$args = [
				'taxonomy'   => 'category',
				'hide_empty' => (bool) ( $input['hide_empty'] ?? false ),
			];
			if ( isset( $input['parent'] ) ) {
				$args['parent'] = absint( $input['parent'] );
			}

			$terms  = get_terms( $args );
			$result = [];

			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					$result[] = [
						'term_id'     => $term->term_id,
						'name'        => $term->name,
						'slug'        => $term->slug,
						'description' => $term->description,
						'parent'      => $term->parent,
						'count'       => $term->count,
					];
				}
			}

			return $result;
		},
		'permission_callback' => function (): bool {
			return current_user_can( 'manage_categories' );
		},
	] );

	wp_register_ability( 'mcpap/create-category', [
		'label'       => __( 'Создать категорию', 'mcp-abilities-provider' ),
		'description' => __( 'Создаёт новую категорию.', 'mcp-abilities-provider' ),
		'category'    => 'taxonomy',
		'meta'        => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		'input_schema' => [
			'type'       => 'object',
			'required'   => [ 'name' ],
			'properties' => [
				'name'        => [ 'type' => 'string', 'description' => 'Название категории' ],
				'slug'        => [ 'type' => 'string', 'description' => 'Slug' ],
				'description' => [ 'type' => 'string', 'description' => 'Описание' ],
				'parent'      => [ 'type' => 'integer', 'description' => 'ID родительской категории' ],
			],
		],
		'execute_callback'    => function ( array $input ): array|WP_Error {
			$args = [];
			if ( isset( $input['slug'] ) )        $args['slug']        = sanitize_title( $input['slug'] );
			if ( isset( $input['description'] ) ) $args['description'] = sanitize_text_field( $input['description'] );
			if ( isset( $input['parent'] ) )      $args['parent']      = absint( $input['parent'] );

			$result = wp_insert_term( sanitize_text_field( $input['name'] ), 'category', $args );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			return [ 'term_id' => $result['term_id'], 'created' => true ];
		},
		'permission_callback' => function (): bool {
			return current_user_can( 'manage_categories' );
		},
	] );

	wp_register_ability( 'mcpap/get-tags', [
		'label'       => __( 'Получить метки', 'mcp-abilities-provider' ),
		'description' => __( 'Возвращает все метки сайта.', 'mcp-abilities-provider' ),
		'category'    => 'taxonomy',
		'readonly'    => true,
		'meta'        => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		'input_schema' => [
			'type'       => 'object',
			'properties' => [
				'hide_empty' => [ 'type' => 'boolean', 'default' => false ],
				'search'     => [ 'type' => 'string', 'description' => 'Поиск по названию' ],
				'number'     => [ 'type' => 'integer', 'default' => 50, 'maximum' => 200 ],
			],
		],
		'execute_callback'    => function ( array $input ): array {
			$args = [
				'taxonomy'   => 'post_tag',
				'hide_empty' => (bool) ( $input['hide_empty'] ?? false ),
				'number'     => min( absint( $input['number'] ?? 50 ), 200 ),
			];
			if ( ! empty( $input['search'] ) ) {
				$args['search'] = sanitize_text_field( $input['search'] );
			}

			$terms  = get_terms( $args );
			$result = [];

			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					$result[] = [
						'term_id' => $term->term_id,
						'name'    => $term->name,
						'slug'    => $term->slug,
						'count'   => $term->count,
					];
				}
			}

			return $result;
		},
		'permission_callback' => function (): bool {
			return current_user_can( 'manage_categories' );
		},
	] );

	wp_register_ability( 'mcpap/create-tag', [
		'label'       => __( 'Создать метку', 'mcp-abilities-provider' ),
		'description' => __( 'Создаёт новую метку.', 'mcp-abilities-provider' ),
		'category'    => 'taxonomy',
		'meta'        => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		'input_schema' => [
			'type'       => 'object',
			'required'   => [ 'name' ],
			'properties' => [
				'name'        => [ 'type' => 'string', 'description' => 'Название метки' ],
				'slug'        => [ 'type' => 'string' ],
				'description' => [ 'type' => 'string' ],
			],
		],
		'execute_callback'    => function ( array $input ): array|WP_Error {
			$args = [];
			if ( isset( $input['slug'] ) )        $args['slug']        = sanitize_title( $input['slug'] );
			if ( isset( $input['description'] ) ) $args['description'] = sanitize_text_field( $input['description'] );

			$result = wp_insert_term( sanitize_text_field( $input['name'] ), 'post_tag', $args );
			if ( is_wp_error( $result ) ) return $result;

			return [ 'term_id' => $result['term_id'], 'created' => true ];
		},
		'permission_callback' => function (): bool {
			return current_user_can( 'manage_categories' );
		},
	] );
}

// ═════════════════════════════════════════════════════════════════════════════
// ПОЛЬЗОВАТЕЛИ
// ═════════════════════════════════════════════════════════════════════════════

/**
 * Регистрирует abilities для работы с пользователями.
 *
 * @since 1.0.0
 */
function mcpap_register_user_abilities(): void {

	wp_register_ability( 'mcpap/get-users', [
		'label'       => __( 'Получить пользователей', 'mcp-abilities-provider' ),
		'description' => __( 'Список пользователей с фильтрацией по роли.', 'mcp-abilities-provider' ),
		'category'    => 'user',
		'readonly'    => true,
		'meta'        => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		'input_schema' => [
			'type'       => 'object',
			'properties' => [
				'role'   => [
					'type'        => 'string',
					'description' => 'Фильтр по роли: administrator, editor, author, contributor, subscriber',
				],
				'number' => [ 'type' => 'integer', 'default' => 20, 'maximum' => 100 ],
				'search' => [ 'type' => 'string', 'description' => 'Поиск по имени или email' ],
			],
		],
		'execute_callback'    => function ( array $input ): array {
			$args = [
				'number' => min( absint( $input['number'] ?? 20 ), 100 ),
			];
			if ( ! empty( $input['role'] ) ) {
				$args['role'] = sanitize_text_field( $input['role'] );
			}
			if ( ! empty( $input['search'] ) ) {
				$args['search'] = '*' . sanitize_text_field( $input['search'] ) . '*';
			}

			$users  = get_users( $args );
			$result = [];

			foreach ( $users as $user ) {
				$result[] = [
					'ID'           => $user->ID,
					'login'        => $user->user_login,
					'display_name' => $user->display_name,
					'email'        => $user->user_email,
					'roles'        => $user->roles,
					'registered'   => $user->user_registered,
					'post_count'   => count_user_posts( $user->ID ),
				];
			}

			return $result;
		},
		'permission_callback' => function (): bool {
			return current_user_can( 'list_users' );
		},
	] );
}

// ═════════════════════════════════════════════════════════════════════════════
// НАСТРОЙКИ
// ═════════════════════════════════════════════════════════════════════════════

/**
 * Регистрирует abilities для работы с настройками.
 *
 * @since 1.0.0
 */
function mcpap_register_settings_abilities(): void {

	wp_register_ability( 'mcpap/get-settings', [
		'label'       => __( 'Получить настройки сайта', 'mcp-abilities-provider' ),
		'description' => __( 'Возвращает основные настройки WordPress: название, описание, URL, часовой пояс, формат даты, структуру ЧПУ, язык.', 'mcp-abilities-provider' ),
		'category'    => 'settings',
		'readonly'    => true,
		'meta'        => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		'input_schema' => [
			'type'       => 'object',
			'properties' => [
				'_placeholder' => [
					'type'        => 'string',
					'description' => 'Не используется, можно не передавать',
				],
			],
		],
		'execute_callback'    => function ( array $input ): array {
			return [
				'blogname'        => get_option( 'blogname' ),
				'blogdescription' => get_option( 'blogdescription' ),
				'siteurl'         => get_option( 'siteurl' ),
				'home'            => get_option( 'home' ),
				'admin_email'     => get_option( 'admin_email' ),
				'language'        => get_locale(),
				'timezone'        => get_option( 'timezone_string' ) ?: 'UTC' . get_option( 'gmt_offset' ),
				'date_format'     => get_option( 'date_format' ),
				'time_format'     => get_option( 'time_format' ),
				'permalink'       => get_option( 'permalink_structure' ),
				'posts_per_page'  => (int) get_option( 'posts_per_page' ),
				'wp_version'      => get_bloginfo( 'version' ),
				'php_version'     => phpversion(),
				'theme'           => get_stylesheet(),
				'is_multisite'    => is_multisite(),
			];
		},
		'permission_callback' => function (): bool {
			return current_user_can( 'manage_options' );
		},
	] );

	wp_register_ability( 'mcpap/update-settings', [
		'label'       => __( 'Обновить настройки сайта', 'mcp-abilities-provider' ),
		'description' => __( 'Обновляет основные настройки: название, описание, часовой пояс, формат даты, количество постов на странице.', 'mcp-abilities-provider' ),
		'category'    => 'settings',
		'meta'        => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		'input_schema' => [
			'type'       => 'object',
			'properties' => [
				'blogname'        => [ 'type' => 'string', 'description' => 'Название сайта' ],
				'blogdescription' => [ 'type' => 'string', 'description' => 'Описание сайта' ],
				'timezone_string' => [ 'type' => 'string', 'description' => 'Часовой пояс (Europe/Moscow)' ],
				'date_format'     => [ 'type' => 'string', 'description' => 'Формат даты' ],
				'time_format'     => [ 'type' => 'string', 'description' => 'Формат времени' ],
				'posts_per_page'  => [ 'type' => 'integer', 'description' => 'Постов на странице' ],
			],
		],
		'execute_callback'    => function ( array $input ): array {
			$allowed = [ 'blogname', 'blogdescription', 'timezone_string', 'date_format', 'time_format', 'posts_per_page' ];
			$updated = [];

			foreach ( $allowed as $key ) {
				if ( isset( $input[ $key ] ) ) {
					$value = 'posts_per_page' === $key ? absint( $input[ $key ] ) : sanitize_text_field( $input[ $key ] );
					update_option( $key, $value );
					$updated[ $key ] = $value;
				}
			}

			return [ 'updated' => $updated ];
		},
		'permission_callback' => function (): bool {
			return current_user_can( 'manage_options' );
		},
	] );
}

// ═════════════════════════════════════════════════════════════════════════════
// МЕНЮ
// ═════════════════════════════════════════════════════════════════════════════

/**
 * Регистрирует abilities для навигационных меню.
 *
 * @since 1.0.0
 */
function mcpap_register_menu_abilities(): void {

	wp_register_ability( 'mcpap/get-menus', [
		'label'       => __( 'Получить меню', 'mcp-abilities-provider' ),
		'description' => __( 'Список всех зарегистрированных навигационных меню и их элементов.', 'mcp-abilities-provider' ),
		'category'    => 'content',
		'readonly'    => true,
		'meta'        => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		'input_schema' => [
			'type'       => 'object',
			'properties' => [
				'_placeholder' => [
					'type'        => 'string',
					'description' => 'Не используется, можно не передавать',
				],
			],
		],
		'execute_callback'    => function ( array $input ): array {
			$menus  = wp_get_nav_menus();
			$result = [];

			foreach ( $menus as $menu ) {
				$items     = wp_get_nav_menu_items( $menu->term_id );
				$locations = get_nav_menu_locations();
				$location  = array_search( $menu->term_id, $locations, true );

				$menu_data = [
					'term_id'  => $menu->term_id,
					'name'     => $menu->name,
					'slug'     => $menu->slug,
					'location' => $location !== false ? $location : null,
					'count'    => $menu->count,
					'items'    => [],
				];

				if ( $items ) {
					foreach ( $items as $item ) {
						$menu_data['items'][] = [
							'ID'        => $item->ID,
							'title'     => $item->title,
							'url'       => $item->url,
							'type'      => $item->type,
							'object'    => $item->object,
							'object_id' => $item->object_id,
							'parent'    => (int) $item->menu_item_parent,
							'order'     => $item->menu_order,
						];
					}
				}

				$result[] = $menu_data;
			}

			return $result;
		},
		'permission_callback' => function (): bool {
			return current_user_can( 'edit_theme_options' );
		},
	] );
}

// ═════════════════════════════════════════════════════════════════════════════
// ПЛАГИНЫ
// ═════════════════════════════════════════════════════════════════════════════

/**
 * Регистрирует abilities для управления плагинами.
 *
 * @since 1.0.0
 */
function mcpap_register_plugin_abilities(): void {

	wp_register_ability( 'mcpap/get-plugins', [
		'label'       => __( 'Получить плагины', 'mcp-abilities-provider' ),
		'description' => __( 'Список установленных плагинов с информацией об активности и версии.', 'mcp-abilities-provider' ),
		'category'    => 'site',
		'readonly'    => true,
		'meta'        => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		'input_schema' => [
			'type'       => 'object',
			'properties' => [
				'status' => [
					'type'        => 'string',
					'description' => 'Фильтр: all, active, inactive',
					'enum'        => [ 'all', 'active', 'inactive' ],
					'default'     => 'all',
				],
			],
		],
		'execute_callback'    => function ( array $input ): array {
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			$all_plugins    = get_plugins();
			$active_plugins = get_option( 'active_plugins', [] );
			$status_filter  = sanitize_text_field( $input['status'] ?? 'all' );
			$result         = [];

			foreach ( $all_plugins as $file => $data ) {
				$is_active = in_array( $file, $active_plugins, true );

				if ( 'active' === $status_filter && ! $is_active ) continue;
				if ( 'inactive' === $status_filter && $is_active ) continue;

				$result[] = [
					'file'        => $file,
					'name'        => $data['Name'] ?? '',
					'version'     => $data['Version'] ?? '',
					'author'      => $data['Author'] ?? '',
					'description' => $data['Description'] ?? '',
					'active'      => $is_active,
					'uri'         => $data['PluginURI'] ?? '',
				];
			}

			return $result;
		},
		'permission_callback' => function (): bool {
			return current_user_can( 'activate_plugins' );
		},
	] );
}

// ═════════════════════════════════════════════════════════════════════════════
// КОММЕНТАРИИ
// ═════════════════════════════════════════════════════════════════════════════

/**
 * Регистрирует abilities для работы с комментариями.
 *
 * @since 1.0.0
 */
function mcpap_register_comment_abilities(): void {

	wp_register_ability( 'mcpap/get-comments', [
		'label'       => __( 'Получить комментарии', 'mcp-abilities-provider' ),
		'description' => __( 'Список комментариев с фильтрацией по статусу и посту.', 'mcp-abilities-provider' ),
		'category'    => 'content',
		'readonly'    => true,
		'meta'        => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		'input_schema' => [
			'type'       => 'object',
			'properties' => [
				'post_id' => [ 'type' => 'integer', 'description' => 'ID поста для фильтрации' ],
				'status'  => [
					'type'    => 'string',
					'default' => 'all',
					'enum'    => [ 'all', 'approve', 'hold', 'spam', 'trash' ],
				],
				'number'  => [ 'type' => 'integer', 'default' => 20, 'maximum' => 100 ],
			],
		],
		'execute_callback'    => function ( array $input ): array {
			$args = [
				'number' => min( absint( $input['number'] ?? 20 ), 100 ),
				'status' => sanitize_text_field( $input['status'] ?? 'all' ),
			];
			if ( ! empty( $input['post_id'] ) ) {
				$args['post_id'] = absint( $input['post_id'] );
			}

			$comments = get_comments( $args );
			$result   = [];

			foreach ( $comments as $comment ) {
				$result[] = [
					'comment_ID'      => $comment->comment_ID,
					'post_id'         => $comment->comment_post_ID,
					'author'          => $comment->comment_author,
					'author_email'    => $comment->comment_author_email,
					'content'         => $comment->comment_content,
					'date'            => $comment->comment_date,
					'approved'        => $comment->comment_approved,
					'parent'          => $comment->comment_parent,
				];
			}

			return $result;
		},
		'permission_callback' => function (): bool {
			return current_user_can( 'moderate_comments' );
		},
	] );

	wp_register_ability( 'mcpap/moderate-comment', [
		'label'       => __( 'Модерировать комментарий', 'mcp-abilities-provider' ),
		'description' => __( 'Одобрить, отклонить, пометить как спам или удалить комментарий.', 'mcp-abilities-provider' ),
		'category'    => 'content',
		'meta'        => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		'input_schema' => [
			'type'       => 'object',
			'required'   => [ 'comment_id', 'action' ],
			'properties' => [
				'comment_id' => [ 'type' => 'integer', 'description' => 'ID комментария' ],
				'action'     => [
					'type'        => 'string',
					'description' => 'Действие: approve, hold, spam, trash',
					'enum'        => [ 'approve', 'hold', 'spam', 'trash' ],
				],
			],
		],
		'execute_callback'    => function ( array $input ): array|WP_Error {
			$comment_id = absint( $input['comment_id'] );
			$comment    = get_comment( $comment_id );

			if ( ! $comment ) {
				return new WP_Error( 'not_found', 'Комментарий не найден' );
			}

			$action  = sanitize_text_field( $input['action'] );
			$status_map = [
				'approve' => '1',
				'hold'    => '0',
				'spam'    => 'spam',
				'trash'   => 'trash',
			];

			if ( ! isset( $status_map[ $action ] ) ) {
				return new WP_Error( 'invalid_action', 'Недопустимое действие' );
			}

			wp_set_comment_status( $comment_id, $status_map[ $action ] );

			return [ 'comment_id' => $comment_id, 'new_status' => $action ];
		},
		'permission_callback' => function (): bool {
			return current_user_can( 'moderate_comments' );
		},
	] );
}

// ═════════════════════════════════════════════════════════════════════════════
// ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ
// ═════════════════════════════════════════════════════════════════════════════

/**
 * Форматирует данные поста для вывода.
 *
 * @since 1.0.0
 *
 * @param WP_Post $post     Объект поста.
 * @param bool    $full     Включать ли полный контент.
 * @return array Отформатированные данные поста.
 */
function mcpap_format_post( WP_Post $post, bool $full = false ): array {
	$data = [
		'ID'         => $post->ID,
		'title'      => $post->post_title,
		'slug'       => $post->post_name,
		'status'     => $post->post_status,
		'date'       => $post->post_date,
		'modified'   => $post->post_modified,
		'author'     => get_the_author_meta( 'display_name', $post->post_author ),
		'url'        => get_permalink( $post ),
		'categories' => [],
		'tags'       => [],
	];

	// Категории
	$cats = get_the_category( $post->ID );
	foreach ( $cats as $cat ) {
		$data['categories'][] = [
			'term_id' => $cat->term_id,
			'name'    => $cat->name,
			'slug'    => $cat->slug,
		];
	}

	// Метки
	$tags = get_the_tags( $post->ID );
	if ( $tags ) {
		foreach ( $tags as $tag ) {
			$data['tags'][] = [
				'term_id' => $tag->term_id,
				'name'    => $tag->name,
				'slug'    => $tag->slug,
			];
		}
	}

	// Миниатюра
	$thumb_id = get_post_thumbnail_id( $post->ID );
	if ( $thumb_id ) {
		$data['featured_image'] = [
			'id'  => $thumb_id,
			'url' => wp_get_attachment_url( $thumb_id ),
		];
	}

	if ( $full ) {
		$data['content'] = $post->post_content;
		$data['excerpt'] = $post->post_excerpt;
		$data['edit_url'] = get_edit_post_link( $post->ID, 'raw' );
	}

	return $data;
}

// ═════════════════════════════════════════════════════════════════════════════
// WOOCOMMERCE: ТОВАРЫ
// ═════════════════════════════════════════════════════════════════════════════

/**
 * Регистрирует abilities для работы с товарами WooCommerce.
 *
 * @since 1.1.0
 */
function mcpap_register_wc_product_abilities(): void {

	// --- Список товаров ---
	wp_register_ability( 'mcpap/wc-get-products', [
		'label'       => __( 'Получить товары WooCommerce', 'mcp-abilities-provider' ),
		'description' => __( 'Список товаров WooCommerce с фильтрацией по категории, статусу, типу, наличию, цене и поиском.', 'mcp-abilities-provider' ),
		'category'    => 'content',
		'readonly'    => true,
		'meta'        => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		'input_schema' => [
			'type'       => 'object',
			'properties' => [
				'per_page' => [
					'type'        => 'integer',
					'description' => 'Количество товаров (по умолчанию 10, максимум 100)',
					'default'     => 10,
					'minimum'     => 1,
					'maximum'     => 100,
				],
				'page' => [
					'type'        => 'integer',
					'description' => 'Номер страницы для пагинации',
					'default'     => 1,
				],
				'status' => [
					'type'        => 'string',
					'description' => 'Статус товара',
					'enum'        => [ 'publish', 'draft', 'pending', 'private', 'trash', 'any' ],
					'default'     => 'publish',
				],
				'category' => [
					'type'        => 'string',
					'description' => 'Slug категории товара',
				],
				'tag' => [
					'type'        => 'string',
					'description' => 'Slug метки товара',
				],
				'type' => [
					'type'        => 'string',
					'description' => 'Тип товара',
					'enum'        => [ 'simple', 'variable', 'grouped', 'external' ],
				],
				'featured' => [
					'type'        => 'boolean',
					'description' => 'Только рекомендуемые товары',
				],
				'on_sale' => [
					'type'        => 'boolean',
					'description' => 'Только товары со скидкой',
				],
				'stock_status' => [
					'type'        => 'string',
					'description' => 'Статус наличия',
					'enum'        => [ 'instock', 'outofstock', 'onbackorder' ],
				],
				'search' => [
					'type'        => 'string',
					'description' => 'Поисковый запрос',
				],
				'orderby' => [
					'type'        => 'string',
					'description' => 'Сортировка',
					'enum'        => [ 'date', 'title', 'price', 'popularity', 'rating', 'menu_order', 'ID' ],
					'default'     => 'date',
				],
				'order' => [
					'type'        => 'string',
					'enum'        => [ 'ASC', 'DESC' ],
					'default'     => 'DESC',
				],
				'sku' => [
					'type'        => 'string',
					'description' => 'Поиск по артикулу (SKU)',
				],
				'min_price' => [
					'type'        => 'number',
					'description' => 'Минимальная цена',
				],
				'max_price' => [
					'type'        => 'number',
					'description' => 'Максимальная цена',
				],
			],
		],
		'execute_callback'    => function ( array $input ): array {
			$args = [
				'limit'      => min( absint( $input['per_page'] ?? 10 ), 100 ),
				'page'       => max( 1, absint( $input['page'] ?? 1 ) ),
				'status'     => sanitize_text_field( $input['status'] ?? 'publish' ),
				'orderby'    => sanitize_text_field( $input['orderby'] ?? 'date' ),
				'order'      => sanitize_text_field( $input['order'] ?? 'DESC' ),
				'return'     => 'objects',
			];

			if ( ! empty( $input['category'] ) ) {
				$args['category'] = [ sanitize_text_field( $input['category'] ) ];
			}
			if ( ! empty( $input['tag'] ) ) {
				$args['tag'] = [ sanitize_text_field( $input['tag'] ) ];
			}
			if ( ! empty( $input['type'] ) ) {
				$args['type'] = sanitize_text_field( $input['type'] );
			}
			if ( isset( $input['featured'] ) ) {
				$args['featured'] = (bool) $input['featured'];
			}
			if ( isset( $input['on_sale'] ) && $input['on_sale'] ) {
				$args['include'] = wc_get_product_ids_on_sale();
			}
			if ( ! empty( $input['stock_status'] ) ) {
				$args['stock_status'] = sanitize_text_field( $input['stock_status'] );
			}
			if ( ! empty( $input['search'] ) ) {
				$args['s'] = sanitize_text_field( $input['search'] );
			}
			if ( ! empty( $input['sku'] ) ) {
				$args['sku'] = sanitize_text_field( $input['sku'] );
			}
			if ( isset( $input['min_price'] ) ) {
				$args['min_price'] = floatval( $input['min_price'] );
			}
			if ( isset( $input['max_price'] ) ) {
				$args['max_price'] = floatval( $input['max_price'] );
			}

			$products = wc_get_products( $args );
			$result   = [];

			foreach ( $products as $product ) {
				$result[] = mcpap_format_wc_product( $product );
			}

			return [
				'products' => $result,
				'total'    => count( $result ),
				'page'     => $args['page'],
			];
		},
		'permission_callback' => function (): bool {
			return current_user_can( 'edit_products' );
		},
	] );

	// --- Один товар по ID ---
	wp_register_ability( 'mcpap/wc-get-product', [
		'label'       => __( 'Получить товар WooCommerce по ID', 'mcp-abilities-provider' ),
		'description' => __( 'Полные данные товара WooCommerce по ID: цена, вариации, атрибуты, галерея, SEO, ACF-поля, наличие.', 'mcp-abilities-provider' ),
		'category'    => 'content',
		'readonly'    => true,
		'meta'        => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		'input_schema' => [
			'type'       => 'object',
			'required'   => [ 'product_id' ],
			'properties' => [
				'product_id' => [
					'type'        => 'integer',
					'description' => 'ID товара',
				],
			],
		],
		'execute_callback'    => function ( array $input ): array|WP_Error {
			$product = wc_get_product( absint( $input['product_id'] ) );
			if ( ! $product ) {
				return new WP_Error( 'not_found', 'Товар не найден' );
			}
			return mcpap_format_wc_product( $product, true );
		},
		'permission_callback' => function (): bool {
			return current_user_can( 'edit_products' );
		},
	] );

	// --- Создать товар ---
	wp_register_ability( 'mcpap/wc-create-product', [
		'label'       => __( 'Создать товар WooCommerce', 'mcp-abilities-provider' ),
		'description' => __( 'Создаёт новый простой или вариативный товар WooCommerce с ценой, описанием, категориями, изображениями и атрибутами.', 'mcp-abilities-provider' ),
		'category'    => 'content',
		'meta'        => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		'input_schema' => [
			'type'       => 'object',
			'required'   => [ 'name' ],
			'properties' => [
				'name' => [
					'type'        => 'string',
					'description' => 'Название товара',
				],
				'type' => [
					'type'        => 'string',
					'description' => 'Тип товара',
					'enum'        => [ 'simple', 'variable', 'grouped', 'external' ],
					'default'     => 'simple',
				],
				'status' => [
					'type'        => 'string',
					'enum'        => [ 'draft', 'publish', 'pending', 'private' ],
					'default'     => 'draft',
				],
				'description' => [
					'type'        => 'string',
					'description' => 'Полное описание товара (HTML)',
				],
				'short_description' => [
					'type'        => 'string',
					'description' => 'Краткое описание',
				],
				'sku' => [
					'type'        => 'string',
					'description' => 'Артикул (SKU)',
				],
				'regular_price' => [
					'type'        => 'string',
					'description' => 'Обычная цена',
				],
				'sale_price' => [
					'type'        => 'string',
					'description' => 'Цена со скидкой',
				],
				'category_ids' => [
					'type'        => 'array',
					'description' => 'Массив ID категорий товаров',
					'items'       => [ 'type' => 'integer' ],
				],
				'tag_ids' => [
					'type'        => 'array',
					'description' => 'Массив ID меток товаров',
					'items'       => [ 'type' => 'integer' ],
				],
				'image_id' => [
					'type'        => 'integer',
					'description' => 'ID основного изображения из медиабиблиотеки',
				],
				'gallery_image_ids' => [
					'type'        => 'array',
					'description' => 'Массив ID изображений галереи',
					'items'       => [ 'type' => 'integer' ],
				],
				'manage_stock' => [
					'type'        => 'boolean',
					'description' => 'Управлять остатками',
					'default'     => false,
				],
				'stock_quantity' => [
					'type'        => 'integer',
					'description' => 'Количество на складе',
				],
				'stock_status' => [
					'type'        => 'string',
					'enum'        => [ 'instock', 'outofstock', 'onbackorder' ],
					'default'     => 'instock',
				],
				'weight' => [
					'type'        => 'string',
					'description' => 'Вес',
				],
				'length' => [ 'type' => 'string', 'description' => 'Длина' ],
				'width'  => [ 'type' => 'string', 'description' => 'Ширина' ],
				'height' => [ 'type' => 'string', 'description' => 'Высота' ],
				'slug' => [
					'type'        => 'string',
					'description' => 'URL-slug товара',
				],
				'featured' => [
					'type'        => 'boolean',
					'description' => 'Рекомендуемый товар',
				],
				'virtual' => [
					'type'        => 'boolean',
					'description' => 'Виртуальный товар (без доставки)',
				],
				'menu_order' => [
					'type'        => 'integer',
					'description' => 'Порядок сортировки',
				],
			],
		],
		'execute_callback'    => function ( array $input ): array|WP_Error {
			$type = sanitize_text_field( $input['type'] ?? 'simple' );

			$product = match ( $type ) {
				'variable' => new WC_Product_Variable(),
				'grouped'  => new WC_Product_Grouped(),
				'external' => new WC_Product_External(),
				default    => new WC_Product_Simple(),
			};

			$product->set_name( sanitize_text_field( $input['name'] ) );
			$product->set_status( sanitize_text_field( $input['status'] ?? 'draft' ) );

			if ( isset( $input['description'] ) )       $product->set_description( wp_kses_post( $input['description'] ) );
			if ( isset( $input['short_description'] ) )  $product->set_short_description( wp_kses_post( $input['short_description'] ) );
			if ( isset( $input['sku'] ) )                $product->set_sku( sanitize_text_field( $input['sku'] ) );
			if ( isset( $input['regular_price'] ) )      $product->set_regular_price( sanitize_text_field( $input['regular_price'] ) );
			if ( isset( $input['sale_price'] ) )         $product->set_sale_price( sanitize_text_field( $input['sale_price'] ) );
			if ( isset( $input['category_ids'] ) )       $product->set_category_ids( array_map( 'absint', $input['category_ids'] ) );
			if ( isset( $input['tag_ids'] ) )            $product->set_tag_ids( array_map( 'absint', $input['tag_ids'] ) );
			if ( isset( $input['image_id'] ) )           $product->set_image_id( absint( $input['image_id'] ) );
			if ( isset( $input['gallery_image_ids'] ) )  $product->set_gallery_image_ids( array_map( 'absint', $input['gallery_image_ids'] ) );
			if ( isset( $input['manage_stock'] ) )       $product->set_manage_stock( (bool) $input['manage_stock'] );
			if ( isset( $input['stock_quantity'] ) )     $product->set_stock_quantity( absint( $input['stock_quantity'] ) );
			if ( isset( $input['stock_status'] ) )       $product->set_stock_status( sanitize_text_field( $input['stock_status'] ) );
			if ( isset( $input['weight'] ) )             $product->set_weight( sanitize_text_field( $input['weight'] ) );
			if ( isset( $input['length'] ) )             $product->set_length( sanitize_text_field( $input['length'] ) );
			if ( isset( $input['width'] ) )              $product->set_width( sanitize_text_field( $input['width'] ) );
			if ( isset( $input['height'] ) )             $product->set_height( sanitize_text_field( $input['height'] ) );
			if ( isset( $input['slug'] ) )               $product->set_slug( sanitize_title( $input['slug'] ) );
			if ( isset( $input['featured'] ) )           $product->set_featured( (bool) $input['featured'] );
			if ( isset( $input['virtual'] ) )            $product->set_virtual( (bool) $input['virtual'] );
			if ( isset( $input['menu_order'] ) )         $product->set_menu_order( absint( $input['menu_order'] ) );

			$product_id = $product->save();

			if ( ! $product_id ) {
				return new WP_Error( 'create_failed', 'Не удалось создать товар' );
			}

			return [
				'product_id' => $product_id,
				'url'        => get_permalink( $product_id ),
				'edit_url'   => get_edit_post_link( $product_id, 'raw' ),
				'status'     => $product->get_status(),
			];
		},
		'permission_callback' => function (): bool {
			return current_user_can( 'publish_products' );
		},
	] );

	// --- Обновить товар ---
	wp_register_ability( 'mcpap/wc-update-product', [
		'label'       => __( 'Обновить товар WooCommerce', 'mcp-abilities-provider' ),
		'description' => __( 'Обновляет существующий товар WooCommerce: название, цену, описание, статус, наличие, категории, изображения, SKU.', 'mcp-abilities-provider' ),
		'category'    => 'content',
		'meta'        => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		'input_schema' => [
			'type'       => 'object',
			'required'   => [ 'product_id' ],
			'properties' => [
				'product_id'        => [ 'type' => 'integer', 'description' => 'ID товара' ],
				'name'              => [ 'type' => 'string', 'description' => 'Новое название' ],
				'status'            => [ 'type' => 'string', 'enum' => [ 'draft', 'publish', 'pending', 'private' ] ],
				'description'       => [ 'type' => 'string', 'description' => 'Новое полное описание (HTML)' ],
				'short_description' => [ 'type' => 'string', 'description' => 'Новое краткое описание' ],
				'sku'               => [ 'type' => 'string', 'description' => 'Новый артикул' ],
				'regular_price'     => [ 'type' => 'string', 'description' => 'Новая обычная цена' ],
				'sale_price'        => [ 'type' => 'string', 'description' => 'Новая цена со скидкой (пустая строка — убрать скидку)' ],
				'category_ids'      => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
				'tag_ids'           => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
				'image_id'          => [ 'type' => 'integer', 'description' => 'ID нового основного изображения (0 — убрать)' ],
				'gallery_image_ids' => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
				'manage_stock'      => [ 'type' => 'boolean' ],
				'stock_quantity'    => [ 'type' => 'integer' ],
				'stock_status'      => [ 'type' => 'string', 'enum' => [ 'instock', 'outofstock', 'onbackorder' ] ],
				'weight'            => [ 'type' => 'string' ],
				'length'            => [ 'type' => 'string' ],
				'width'             => [ 'type' => 'string' ],
				'height'            => [ 'type' => 'string' ],
				'slug'              => [ 'type' => 'string' ],
				'featured'          => [ 'type' => 'boolean' ],
				'virtual'           => [ 'type' => 'boolean' ],
				'menu_order'        => [ 'type' => 'integer' ],
			],
		],
		'execute_callback'    => function ( array $input ): array|WP_Error {
			$product = wc_get_product( absint( $input['product_id'] ) );
			if ( ! $product ) {
				return new WP_Error( 'not_found', 'Товар не найден' );
			}

			if ( isset( $input['name'] ) )              $product->set_name( sanitize_text_field( $input['name'] ) );
			if ( isset( $input['status'] ) )             $product->set_status( sanitize_text_field( $input['status'] ) );
			if ( isset( $input['description'] ) )        $product->set_description( wp_kses_post( $input['description'] ) );
			if ( isset( $input['short_description'] ) )  $product->set_short_description( wp_kses_post( $input['short_description'] ) );
			if ( isset( $input['sku'] ) )                $product->set_sku( sanitize_text_field( $input['sku'] ) );
			if ( isset( $input['regular_price'] ) )      $product->set_regular_price( sanitize_text_field( $input['regular_price'] ) );
			if ( isset( $input['sale_price'] ) )         $product->set_sale_price( sanitize_text_field( $input['sale_price'] ) );
			if ( isset( $input['category_ids'] ) )       $product->set_category_ids( array_map( 'absint', $input['category_ids'] ) );
			if ( isset( $input['tag_ids'] ) )            $product->set_tag_ids( array_map( 'absint', $input['tag_ids'] ) );
			if ( isset( $input['image_id'] ) )           $product->set_image_id( absint( $input['image_id'] ) );
			if ( isset( $input['gallery_image_ids'] ) )  $product->set_gallery_image_ids( array_map( 'absint', $input['gallery_image_ids'] ) );
			if ( isset( $input['manage_stock'] ) )       $product->set_manage_stock( (bool) $input['manage_stock'] );
			if ( isset( $input['stock_quantity'] ) )     $product->set_stock_quantity( absint( $input['stock_quantity'] ) );
			if ( isset( $input['stock_status'] ) )       $product->set_stock_status( sanitize_text_field( $input['stock_status'] ) );
			if ( isset( $input['weight'] ) )             $product->set_weight( sanitize_text_field( $input['weight'] ) );
			if ( isset( $input['length'] ) )             $product->set_length( sanitize_text_field( $input['length'] ) );
			if ( isset( $input['width'] ) )              $product->set_width( sanitize_text_field( $input['width'] ) );
			if ( isset( $input['height'] ) )             $product->set_height( sanitize_text_field( $input['height'] ) );
			if ( isset( $input['slug'] ) )               $product->set_slug( sanitize_title( $input['slug'] ) );
			if ( isset( $input['featured'] ) )           $product->set_featured( (bool) $input['featured'] );
			if ( isset( $input['virtual'] ) )            $product->set_virtual( (bool) $input['virtual'] );
			if ( isset( $input['menu_order'] ) )         $product->set_menu_order( absint( $input['menu_order'] ) );

			$product->save();

			return [
				'product_id' => $product->get_id(),
				'url'        => get_permalink( $product->get_id() ),
				'updated'    => true,
			];
		},
		'permission_callback' => function (): bool {
			return current_user_can( 'edit_products' );
		},
	] );

	// --- Удалить товар ---
	wp_register_ability( 'mcpap/wc-delete-product', [
		'label'       => __( 'Удалить товар WooCommerce', 'mcp-abilities-provider' ),
		'description' => __( 'Перемещает товар в корзину или удаляет окончательно.', 'mcp-abilities-provider' ),
		'category'    => 'content',
		'meta'        => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		'input_schema' => [
			'type'       => 'object',
			'required'   => [ 'product_id' ],
			'properties' => [
				'product_id'   => [ 'type' => 'integer', 'description' => 'ID товара' ],
				'force_delete' => [ 'type' => 'boolean', 'default' => false, 'description' => 'true — удалить навсегда, false — в корзину' ],
			],
		],
		'execute_callback'    => function ( array $input ): array|WP_Error {
			$product = wc_get_product( absint( $input['product_id'] ) );
			if ( ! $product ) {
				return new WP_Error( 'not_found', 'Товар не найден' );
			}

			$force = (bool) ( $input['force_delete'] ?? false );
			$product->delete( $force );

			return [
				'product_id' => absint( $input['product_id'] ),
				'deleted'    => true,
				'trashed'    => ! $force,
			];
		},
		'permission_callback' => function (): bool {
			return current_user_can( 'delete_products' );
		},
	] );
}

// ═════════════════════════════════════════════════════════════════════════════
// WOOCOMMERCE: КАТЕГОРИИ И МЕТКИ ТОВАРОВ
// ═════════════════════════════════════════════════════════════════════════════

/**
 * Регистрирует abilities для категорий и меток товаров WooCommerce.
 *
 * @since 1.1.0
 */
function mcpap_register_wc_product_taxonomy_abilities(): void {

	// --- Категории товаров ---
	wp_register_ability( 'mcpap/wc-get-product-categories', [
		'label'       => __( 'Получить категории товаров', 'mcp-abilities-provider' ),
		'description' => __( 'Возвращает дерево категорий товаров WooCommerce с количеством товаров, описаниями и изображениями.', 'mcp-abilities-provider' ),
		'category'    => 'taxonomy',
		'readonly'    => true,
		'meta'        => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		'input_schema' => [
			'type'       => 'object',
			'properties' => [
				'hide_empty' => [ 'type' => 'boolean', 'default' => false, 'description' => 'Скрыть пустые категории' ],
				'parent'     => [ 'type' => 'integer', 'description' => 'ID родительской категории (0 — только верхний уровень)' ],
			],
		],
		'execute_callback'    => function ( array $input ): array {
			$args = [
				'taxonomy'   => 'product_cat',
				'hide_empty' => (bool) ( $input['hide_empty'] ?? false ),
				'orderby'    => 'name',
			];
			if ( isset( $input['parent'] ) ) {
				$args['parent'] = absint( $input['parent'] );
			}

			$terms  = get_terms( $args );
			$result = [];

			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					$thumb_id = get_term_meta( $term->term_id, 'thumbnail_id', true );
					$item = [
						'term_id'     => $term->term_id,
						'name'        => $term->name,
						'slug'        => $term->slug,
						'description' => $term->description,
						'parent'      => $term->parent,
						'count'       => $term->count,
						'url'         => get_term_link( $term ),
					];
					if ( $thumb_id ) {
						$item['image_url'] = wp_get_attachment_url( $thumb_id );
					}
					$result[] = $item;
				}
			}

			return $result;
		},
		'permission_callback' => function (): bool {
			return current_user_can( 'manage_product_terms' );
		},
	] );

	// --- Создать категорию товаров ---
	wp_register_ability( 'mcpap/wc-create-product-category', [
		'label'       => __( 'Создать категорию товаров', 'mcp-abilities-provider' ),
		'description' => __( 'Создаёт новую категорию товаров WooCommerce.', 'mcp-abilities-provider' ),
		'category'    => 'taxonomy',
		'meta'        => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		'input_schema' => [
			'type'       => 'object',
			'required'   => [ 'name' ],
			'properties' => [
				'name'        => [ 'type' => 'string', 'description' => 'Название категории' ],
				'slug'        => [ 'type' => 'string', 'description' => 'Slug' ],
				'description' => [ 'type' => 'string', 'description' => 'Описание' ],
				'parent'      => [ 'type' => 'integer', 'description' => 'ID родительской категории' ],
				'image_id'    => [ 'type' => 'integer', 'description' => 'ID изображения из медиабиблиотеки' ],
			],
		],
		'execute_callback'    => function ( array $input ): array|WP_Error {
			$args = [];
			if ( isset( $input['slug'] ) )        $args['slug']        = sanitize_title( $input['slug'] );
			if ( isset( $input['description'] ) ) $args['description'] = sanitize_text_field( $input['description'] );
			if ( isset( $input['parent'] ) )      $args['parent']      = absint( $input['parent'] );

			$result = wp_insert_term( sanitize_text_field( $input['name'] ), 'product_cat', $args );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			if ( ! empty( $input['image_id'] ) ) {
				update_term_meta( $result['term_id'], 'thumbnail_id', absint( $input['image_id'] ) );
			}

			return [
				'term_id' => $result['term_id'],
				'url'     => get_term_link( $result['term_id'], 'product_cat' ),
				'created' => true,
			];
		},
		'permission_callback' => function (): bool {
			return current_user_can( 'manage_product_terms' );
		},
	] );

	// --- Метки товаров ---
	wp_register_ability( 'mcpap/wc-get-product-tags', [
		'label'       => __( 'Получить метки товаров', 'mcp-abilities-provider' ),
		'description' => __( 'Возвращает метки товаров WooCommerce.', 'mcp-abilities-provider' ),
		'category'    => 'taxonomy',
		'readonly'    => true,
		'meta'        => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		'input_schema' => [
			'type'       => 'object',
			'properties' => [
				'hide_empty' => [ 'type' => 'boolean', 'default' => false ],
				'number'     => [ 'type' => 'integer', 'default' => 50, 'maximum' => 200 ],
				'search'     => [ 'type' => 'string', 'description' => 'Поиск по названию' ],
			],
		],
		'execute_callback'    => function ( array $input ): array {
			$args = [
				'taxonomy'   => 'product_tag',
				'hide_empty' => (bool) ( $input['hide_empty'] ?? false ),
				'number'     => min( absint( $input['number'] ?? 50 ), 200 ),
			];
			if ( ! empty( $input['search'] ) ) {
				$args['search'] = sanitize_text_field( $input['search'] );
			}

			$terms  = get_terms( $args );
			$result = [];

			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					$result[] = [
						'term_id' => $term->term_id,
						'name'    => $term->name,
						'slug'    => $term->slug,
						'count'   => $term->count,
					];
				}
			}

			return $result;
		},
		'permission_callback' => function (): bool {
			return current_user_can( 'manage_product_terms' );
		},
	] );

	// --- Создать метку товаров ---
	wp_register_ability( 'mcpap/wc-create-product-tag', [
		'label'       => __( 'Создать метку товара', 'mcp-abilities-provider' ),
		'description' => __( 'Создаёт новую метку товаров WooCommerce.', 'mcp-abilities-provider' ),
		'category'    => 'taxonomy',
		'meta'        => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		'input_schema' => [
			'type'       => 'object',
			'required'   => [ 'name' ],
			'properties' => [
				'name'        => [ 'type' => 'string', 'description' => 'Название метки' ],
				'slug'        => [ 'type' => 'string' ],
				'description' => [ 'type' => 'string' ],
			],
		],
		'execute_callback'    => function ( array $input ): array|WP_Error {
			$args = [];
			if ( isset( $input['slug'] ) )        $args['slug']        = sanitize_title( $input['slug'] );
			if ( isset( $input['description'] ) ) $args['description'] = sanitize_text_field( $input['description'] );

			$result = wp_insert_term( sanitize_text_field( $input['name'] ), 'product_tag', $args );
			if ( is_wp_error( $result ) ) return $result;

			return [ 'term_id' => $result['term_id'], 'created' => true ];
		},
		'permission_callback' => function (): bool {
			return current_user_can( 'manage_product_terms' );
		},
	] );
}

// ═════════════════════════════════════════════════════════════════════════════
// WOOCOMMERCE: ЗАКАЗЫ
// ═════════════════════════════════════════════════════════════════════════════

/**
 * Регистрирует abilities для работы с заказами WooCommerce.
 *
 * @since 1.1.0
 */
function mcpap_register_wc_order_abilities(): void {

	// --- Список заказов ---
	wp_register_ability( 'mcpap/wc-get-orders', [
		'label'       => __( 'Получить заказы WooCommerce', 'mcp-abilities-provider' ),
		'description' => __( 'Список заказов с фильтрацией по статусу, дате, клиенту и сумме.', 'mcp-abilities-provider' ),
		'category'    => 'content',
		'readonly'    => true,
		'meta'        => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		'input_schema' => [
			'type'       => 'object',
			'properties' => [
				'per_page' => [
					'type'    => 'integer',
					'default' => 10,
					'maximum' => 100,
				],
				'page' => [
					'type'    => 'integer',
					'default' => 1,
				],
				'status' => [
					'type'        => 'string',
					'description' => 'Статус заказа',
					'enum'        => [ 'any', 'pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed', 'trash' ],
					'default'     => 'any',
				],
				'customer_id' => [
					'type'        => 'integer',
					'description' => 'ID клиента',
				],
				'date_after' => [
					'type'        => 'string',
					'description' => 'Заказы после даты (YYYY-MM-DD)',
				],
				'date_before' => [
					'type'        => 'string',
					'description' => 'Заказы до даты (YYYY-MM-DD)',
				],
				'search' => [
					'type'        => 'string',
					'description' => 'Поиск по номеру заказа, email или имени клиента',
				],
				'orderby' => [
					'type'    => 'string',
					'enum'    => [ 'date', 'id', 'total' ],
					'default' => 'date',
				],
				'order' => [
					'type'    => 'string',
					'enum'    => [ 'ASC', 'DESC' ],
					'default' => 'DESC',
				],
			],
		],
		'execute_callback'    => function ( array $input ): array {
			$args = [
				'limit'   => min( absint( $input['per_page'] ?? 10 ), 100 ),
				'page'    => max( 1, absint( $input['page'] ?? 1 ) ),
				'orderby' => sanitize_text_field( $input['orderby'] ?? 'date' ),
				'order'   => sanitize_text_field( $input['order'] ?? 'DESC' ),
				'return'  => 'objects',
			];

			$status = sanitize_text_field( $input['status'] ?? 'any' );
			if ( 'any' !== $status ) {
				$args['status'] = $status;
			}
			if ( ! empty( $input['customer_id'] ) ) {
				$args['customer_id'] = absint( $input['customer_id'] );
			}
			if ( ! empty( $input['date_after'] ) ) {
				$args['date_after'] = sanitize_text_field( $input['date_after'] );
			}
			if ( ! empty( $input['date_before'] ) ) {
				$args['date_before'] = sanitize_text_field( $input['date_before'] );
			}
			if ( ! empty( $input['search'] ) ) {
				$args['s'] = sanitize_text_field( $input['search'] );
			}

			$orders = wc_get_orders( $args );
			$result = [];

			foreach ( $orders as $order ) {
				$result[] = mcpap_format_wc_order( $order );
			}

			return [
				'orders' => $result,
				'total'  => count( $result ),
				'page'   => $args['page'],
			];
		},
		'permission_callback' => function (): bool {
			return current_user_can( 'edit_shop_orders' );
		},
	] );

	// --- Один заказ ---
	wp_register_ability( 'mcpap/wc-get-order', [
		'label'       => __( 'Получить заказ WooCommerce по ID', 'mcp-abilities-provider' ),
		'description' => __( 'Полные данные заказа: товары, клиент, адрес доставки, оплата, заметки.', 'mcp-abilities-provider' ),
		'category'    => 'content',
		'readonly'    => true,
		'meta'        => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		'input_schema' => [
			'type'       => 'object',
			'required'   => [ 'order_id' ],
			'properties' => [
				'order_id' => [ 'type' => 'integer', 'description' => 'ID заказа' ],
			],
		],
		'execute_callback'    => function ( array $input ): array|WP_Error {
			$order = wc_get_order( absint( $input['order_id'] ) );
			if ( ! $order ) {
				return new WP_Error( 'not_found', 'Заказ не найден' );
			}
			return mcpap_format_wc_order( $order, true );
		},
		'permission_callback' => function (): bool {
			return current_user_can( 'edit_shop_orders' );
		},
	] );

	// --- Обновить статус заказа ---
	wp_register_ability( 'mcpap/wc-update-order-status', [
		'label'       => __( 'Обновить статус заказа', 'mcp-abilities-provider' ),
		'description' => __( 'Меняет статус заказа WooCommerce и опционально добавляет заметку.', 'mcp-abilities-provider' ),
		'category'    => 'content',
		'meta'        => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		'input_schema' => [
			'type'       => 'object',
			'required'   => [ 'order_id', 'status' ],
			'properties' => [
				'order_id' => [ 'type' => 'integer', 'description' => 'ID заказа' ],
				'status'   => [
					'type'        => 'string',
					'description' => 'Новый статус',
					'enum'        => [ 'pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed' ],
				],
				'note' => [
					'type'        => 'string',
					'description' => 'Заметка к заказу (видна клиенту при customer_note=true)',
				],
				'customer_note' => [
					'type'        => 'boolean',
					'description' => 'Отправить заметку клиенту',
					'default'     => false,
				],
			],
		],
		'execute_callback'    => function ( array $input ): array|WP_Error {
			$order = wc_get_order( absint( $input['order_id'] ) );
			if ( ! $order ) {
				return new WP_Error( 'not_found', 'Заказ не найден' );
			}

			$old_status = $order->get_status();
			$new_status = sanitize_text_field( $input['status'] );

			$order->update_status( $new_status, sanitize_text_field( $input['note'] ?? '' ) );

			if ( ! empty( $input['note'] ) && ! empty( $input['customer_note'] ) ) {
				$order->add_order_note( sanitize_text_field( $input['note'] ), true );
			}

			return [
				'order_id'   => $order->get_id(),
				'old_status' => $old_status,
				'new_status' => $new_status,
				'updated'    => true,
			];
		},
		'permission_callback' => function (): bool {
			return current_user_can( 'edit_shop_orders' );
		},
	] );
}

// ═════════════════════════════════════════════════════════════════════════════
// WOOCOMMERCE: КУПОНЫ
// ═════════════════════════════════════════════════════════════════════════════

/**
 * Регистрирует abilities для работы с купонами WooCommerce.
 *
 * @since 1.1.0
 */
function mcpap_register_wc_coupon_abilities(): void {

	// --- Список купонов ---
	wp_register_ability( 'mcpap/wc-get-coupons', [
		'label'       => __( 'Получить купоны WooCommerce', 'mcp-abilities-provider' ),
		'description' => __( 'Список всех купонов WooCommerce с информацией о скидке, использовании и сроке действия.', 'mcp-abilities-provider' ),
		'category'    => 'content',
		'readonly'    => true,
		'meta'        => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		'input_schema' => [
			'type'       => 'object',
			'properties' => [
				'per_page' => [ 'type' => 'integer', 'default' => 20, 'maximum' => 100 ],
				'search'   => [ 'type' => 'string', 'description' => 'Поиск по коду купона' ],
			],
		],
		'execute_callback'    => function ( array $input ): array {
			$args = [
				'posts_per_page' => min( absint( $input['per_page'] ?? 20 ), 100 ),
				'post_type'      => 'shop_coupon',
				'post_status'    => 'publish',
				'orderby'        => 'date',
				'order'          => 'DESC',
			];
			if ( ! empty( $input['search'] ) ) {
				$args['s'] = sanitize_text_field( $input['search'] );
			}

			$posts  = get_posts( $args );
			$result = [];

			foreach ( $posts as $post ) {
				$coupon = new WC_Coupon( $post->ID );
				$result[] = [
					'id'                => $coupon->get_id(),
					'code'              => $coupon->get_code(),
					'discount_type'     => $coupon->get_discount_type(),
					'amount'            => $coupon->get_amount(),
					'description'       => $coupon->get_description(),
					'usage_count'       => $coupon->get_usage_count(),
					'usage_limit'       => $coupon->get_usage_limit(),
					'date_expires'      => $coupon->get_date_expires() ? $coupon->get_date_expires()->date( 'Y-m-d H:i:s' ) : null,
					'free_shipping'     => $coupon->get_free_shipping(),
					'minimum_amount'    => $coupon->get_minimum_amount(),
					'maximum_amount'    => $coupon->get_maximum_amount(),
				];
			}

			return $result;
		},
		'permission_callback' => function (): bool {
			return current_user_can( 'manage_woocommerce' );
		},
	] );

	// --- Создать купон ---
	wp_register_ability( 'mcpap/wc-create-coupon', [
		'label'       => __( 'Создать купон WooCommerce', 'mcp-abilities-provider' ),
		'description' => __( 'Создаёт новый купон WooCommerce с настройками скидки, ограничений и срока действия.', 'mcp-abilities-provider' ),
		'category'    => 'content',
		'meta'        => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		'input_schema' => [
			'type'       => 'object',
			'required'   => [ 'code', 'discount_type', 'amount' ],
			'properties' => [
				'code' => [
					'type'        => 'string',
					'description' => 'Код купона (уникальный)',
				],
				'discount_type' => [
					'type'        => 'string',
					'description' => 'Тип скидки',
					'enum'        => [ 'percent', 'fixed_cart', 'fixed_product' ],
				],
				'amount' => [
					'type'        => 'string',
					'description' => 'Размер скидки',
				],
				'description' => [
					'type'        => 'string',
					'description' => 'Описание купона',
				],
				'date_expires' => [
					'type'        => 'string',
					'description' => 'Дата истечения (YYYY-MM-DD)',
				],
				'usage_limit' => [
					'type'        => 'integer',
					'description' => 'Максимальное количество использований',
				],
				'usage_limit_per_user' => [
					'type'        => 'integer',
					'description' => 'Лимит использований на пользователя',
				],
				'free_shipping' => [
					'type'        => 'boolean',
					'description' => 'Бесплатная доставка',
					'default'     => false,
				],
				'minimum_amount' => [
					'type'        => 'string',
					'description' => 'Минимальная сумма заказа',
				],
				'maximum_amount' => [
					'type'        => 'string',
					'description' => 'Максимальная сумма заказа',
				],
				'individual_use' => [
					'type'        => 'boolean',
					'description' => 'Только индивидуальное использование (нельзя с другими купонами)',
					'default'     => false,
				],
				'product_ids' => [
					'type'        => 'array',
					'description' => 'ID товаров, на которые действует купон',
					'items'       => [ 'type' => 'integer' ],
				],
				'excluded_product_ids' => [
					'type'        => 'array',
					'description' => 'ID исключённых товаров',
					'items'       => [ 'type' => 'integer' ],
				],
				'product_categories' => [
					'type'        => 'array',
					'description' => 'ID категорий товаров',
					'items'       => [ 'type' => 'integer' ],
				],
			],
		],
		'execute_callback'    => function ( array $input ): array|WP_Error {
			$coupon = new WC_Coupon();

			$coupon->set_code( sanitize_text_field( $input['code'] ) );
			$coupon->set_discount_type( sanitize_text_field( $input['discount_type'] ) );
			$coupon->set_amount( sanitize_text_field( $input['amount'] ) );

			if ( isset( $input['description'] ) )          $coupon->set_description( sanitize_text_field( $input['description'] ) );
			if ( isset( $input['date_expires'] ) )         $coupon->set_date_expires( sanitize_text_field( $input['date_expires'] ) );
			if ( isset( $input['usage_limit'] ) )          $coupon->set_usage_limit( absint( $input['usage_limit'] ) );
			if ( isset( $input['usage_limit_per_user'] ) ) $coupon->set_usage_limit_per_user( absint( $input['usage_limit_per_user'] ) );
			if ( isset( $input['free_shipping'] ) )        $coupon->set_free_shipping( (bool) $input['free_shipping'] );
			if ( isset( $input['minimum_amount'] ) )       $coupon->set_minimum_amount( sanitize_text_field( $input['minimum_amount'] ) );
			if ( isset( $input['maximum_amount'] ) )       $coupon->set_maximum_amount( sanitize_text_field( $input['maximum_amount'] ) );
			if ( isset( $input['individual_use'] ) )       $coupon->set_individual_use( (bool) $input['individual_use'] );
			if ( isset( $input['product_ids'] ) )          $coupon->set_product_ids( array_map( 'absint', $input['product_ids'] ) );
			if ( isset( $input['excluded_product_ids'] ) ) $coupon->set_excluded_product_ids( array_map( 'absint', $input['excluded_product_ids'] ) );
			if ( isset( $input['product_categories'] ) )   $coupon->set_product_categories( array_map( 'absint', $input['product_categories'] ) );

			$coupon_id = $coupon->save();

			if ( ! $coupon_id ) {
				return new WP_Error( 'create_failed', 'Не удалось создать купон' );
			}

			return [
				'coupon_id'     => $coupon_id,
				'code'          => $coupon->get_code(),
				'discount_type' => $coupon->get_discount_type(),
				'amount'        => $coupon->get_amount(),
				'created'       => true,
			];
		},
		'permission_callback' => function (): bool {
			return current_user_can( 'manage_woocommerce' );
		},
	] );
}

// ═════════════════════════════════════════════════════════════════════════════
// WOOCOMMERCE: ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ
// ═════════════════════════════════════════════════════════════════════════════

/**
 * Форматирует данные товара WooCommerce.
 *
 * @since 1.1.0
 *
 * @param WC_Product $product Объект товара.
 * @param bool       $full    Включать полные данные.
 * @return array
 */
function mcpap_format_wc_product( WC_Product $product, bool $full = false ): array {
	$data = [
		'ID'              => $product->get_id(),
		'name'            => $product->get_name(),
		'slug'            => $product->get_slug(),
		'type'            => $product->get_type(),
		'status'          => $product->get_status(),
		'sku'             => $product->get_sku(),
		'price'           => $product->get_price(),
		'regular_price'   => $product->get_regular_price(),
		'sale_price'      => $product->get_sale_price(),
		'on_sale'         => $product->is_on_sale(),
		'stock_status'    => $product->get_stock_status(),
		'stock_quantity'  => $product->get_stock_quantity(),
		'url'             => get_permalink( $product->get_id() ),
		'categories'      => [],
		'featured'        => $product->is_featured(),
	];

	// Изображение
	$image_id = $product->get_image_id();
	if ( $image_id ) {
		$data['image'] = [
			'id'  => $image_id,
			'url' => wp_get_attachment_url( $image_id ),
		];
	}

	// Категории
	$cat_ids = $product->get_category_ids();
	foreach ( $cat_ids as $cat_id ) {
		$term = get_term( $cat_id, 'product_cat' );
		if ( $term && ! is_wp_error( $term ) ) {
			$data['categories'][] = [
				'term_id' => $term->term_id,
				'name'    => $term->name,
				'slug'    => $term->slug,
			];
		}
	}

	if ( $full ) {
		$data['description']       = $product->get_description();
		$data['short_description'] = $product->get_short_description();
		$data['weight']            = $product->get_weight();
		$data['length']            = $product->get_length();
		$data['width']             = $product->get_width();
		$data['height']            = $product->get_height();
		$data['manage_stock']      = $product->get_manage_stock();
		$data['virtual']           = $product->is_virtual();
		$data['menu_order']        = $product->get_menu_order();
		$data['date_created']      = $product->get_date_created() ? $product->get_date_created()->date( 'Y-m-d H:i:s' ) : null;
		$data['date_modified']     = $product->get_date_modified() ? $product->get_date_modified()->date( 'Y-m-d H:i:s' ) : null;
		$data['edit_url']          = get_edit_post_link( $product->get_id(), 'raw' );
		$data['total_sales']       = $product->get_total_sales();
		$data['average_rating']    = $product->get_average_rating();
		$data['review_count']      = $product->get_review_count();

		// Галерея
		$gallery_ids = $product->get_gallery_image_ids();
		if ( $gallery_ids ) {
			$data['gallery'] = [];
			foreach ( $gallery_ids as $gid ) {
				$data['gallery'][] = [
					'id'  => $gid,
					'url' => wp_get_attachment_url( $gid ),
				];
			}
		}

		// Метки
		$tag_ids = $product->get_tag_ids();
		if ( $tag_ids ) {
			$data['tags'] = [];
			foreach ( $tag_ids as $tag_id ) {
				$term = get_term( $tag_id, 'product_tag' );
				if ( $term && ! is_wp_error( $term ) ) {
					$data['tags'][] = [
						'term_id' => $term->term_id,
						'name'    => $term->name,
						'slug'    => $term->slug,
					];
				}
			}
		}

		// Атрибуты
		$attributes = $product->get_attributes();
		if ( $attributes ) {
			$data['attributes'] = [];
			foreach ( $attributes as $attr ) {
				if ( $attr instanceof WC_Product_Attribute ) {
					$data['attributes'][] = [
						'name'      => $attr->get_name(),
						'options'   => $attr->get_options(),
						'visible'   => $attr->get_visible(),
						'variation' => $attr->get_variation(),
					];
				}
			}
		}

		// Вариации (для variable products)
		if ( $product->is_type( 'variable' ) ) {
			$variation_ids = $product->get_children();
			$data['variations'] = [];
			foreach ( $variation_ids as $var_id ) {
				$variation = wc_get_product( $var_id );
				if ( $variation ) {
					$data['variations'][] = [
						'ID'             => $variation->get_id(),
						'sku'            => $variation->get_sku(),
						'price'          => $variation->get_price(),
						'regular_price'  => $variation->get_regular_price(),
						'sale_price'     => $variation->get_sale_price(),
						'stock_status'   => $variation->get_stock_status(),
						'stock_quantity' => $variation->get_stock_quantity(),
						'attributes'     => $variation->get_attributes(),
						'image_url'      => wp_get_attachment_url( $variation->get_image_id() ),
					];
				}
			}
		}
	}

	return $data;
}

/**
 * Форматирует данные заказа WooCommerce.
 *
 * @since 1.1.0
 *
 * @param WC_Order $order Объект заказа.
 * @param bool     $full  Включать полные данные.
 * @return array
 */
function mcpap_format_wc_order( WC_Order $order, bool $full = false ): array {
	$data = [
		'ID'             => $order->get_id(),
		'number'         => $order->get_order_number(),
		'status'         => $order->get_status(),
		'total'          => $order->get_total(),
		'currency'       => $order->get_currency(),
		'date_created'   => $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i:s' ) : null,
		'payment_method' => $order->get_payment_method_title(),
		'customer_name'  => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
		'customer_email' => $order->get_billing_email(),
		'items_count'    => $order->get_item_count(),
	];

	if ( $full ) {
		$data['billing'] = [
			'first_name' => $order->get_billing_first_name(),
			'last_name'  => $order->get_billing_last_name(),
			'email'      => $order->get_billing_email(),
			'phone'      => $order->get_billing_phone(),
			'address_1'  => $order->get_billing_address_1(),
			'address_2'  => $order->get_billing_address_2(),
			'city'       => $order->get_billing_city(),
			'state'      => $order->get_billing_state(),
			'postcode'   => $order->get_billing_postcode(),
			'country'    => $order->get_billing_country(),
		];

		$data['shipping'] = [
			'first_name' => $order->get_shipping_first_name(),
			'last_name'  => $order->get_shipping_last_name(),
			'address_1'  => $order->get_shipping_address_1(),
			'address_2'  => $order->get_shipping_address_2(),
			'city'       => $order->get_shipping_city(),
			'state'      => $order->get_shipping_state(),
			'postcode'   => $order->get_shipping_postcode(),
			'country'    => $order->get_shipping_country(),
		];

		$data['items'] = [];
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			$data['items'][] = [
				'name'       => $item->get_name(),
				'product_id' => $item->get_product_id(),
				'variation_id' => $item->get_variation_id(),
				'quantity'   => $item->get_quantity(),
				'subtotal'   => $item->get_subtotal(),
				'total'      => $item->get_total(),
				'sku'        => $product ? $product->get_sku() : '',
			];
		}

		$data['subtotal']        = $order->get_subtotal();
		$data['shipping_total']  = $order->get_shipping_total();
		$data['discount_total']  = $order->get_discount_total();
		$data['total_tax']       = $order->get_total_tax();
		$data['customer_id']     = $order->get_customer_id();
		$data['customer_note']   = $order->get_customer_note();
		$data['date_paid']       = $order->get_date_paid() ? $order->get_date_paid()->date( 'Y-m-d H:i:s' ) : null;
		$data['date_completed']  = $order->get_date_completed() ? $order->get_date_completed()->date( 'Y-m-d H:i:s' ) : null;
		$data['edit_url']        = $order->get_edit_order_url();

		// Заметки заказа
		$notes = wc_get_order_notes( [ 'order_id' => $order->get_id(), 'limit' => 10 ] );
		$data['notes'] = [];
		foreach ( $notes as $note ) {
			$data['notes'][] = [
				'content'       => $note->content,
				'date'          => $note->date_created->date( 'Y-m-d H:i:s' ),
				'customer_note' => $note->customer_note,
				'added_by'      => $note->added_by,
			];
		}

		// Купоны
		$coupons = $order->get_coupon_codes();
		if ( $coupons ) {
			$data['coupons'] = $coupons;
		}
	}

	return $data;
}

// ═════════════════════════════════════════════════════════════════════════════
// CLASSIFIED LISTING: ОБЪЯВЛЕНИЯ
// ═════════════════════════════════════════════════════════════════════════════

/**
 * Регистрирует abilities для работы с объявлениями Classified Listing.
 *
 * @since 1.2.0
 */
function mcpap_register_rtcl_listing_abilities(): void {

	// --- Список объявлений ---
	wp_register_ability( 'mcpap/rtcl-get-listings', [
		'label'       => __( 'Получить объявления', 'mcp-abilities-provider' ),
		'description' => __( 'Список объявлений Classified Listing с фильтрацией по категории, локации, типу, статусу, цене, featured-флагу и поисковому запросу.', 'mcp-abilities-provider' ),
		'category'    => 'classified',
		'readonly'    => true,
		'meta'        => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		'input_schema' => [
			'type'       => 'object',
			'properties' => [
				'per_page' => [
					'type'        => 'integer',
					'description' => 'Количество объявлений (по умолчанию 10, максимум 100)',
					'default'     => 10,
					'minimum'     => 1,
					'maximum'     => 100,
				],
				'page' => [
					'type'        => 'integer',
					'description' => 'Номер страницы для пагинации',
					'default'     => 1,
				],
				'status' => [
					'type'        => 'string',
					'description' => 'Статус объявления',
					'enum'        => [ 'publish', 'draft', 'pending', 'private', 'expired', 'rtcl-reviewed', 'any' ],
					'default'     => 'publish',
				],
				'category' => [
					'type'        => 'integer',
					'description' => 'ID категории (rtcl_category)',
				],
				'category_slug' => [
					'type'        => 'string',
					'description' => 'Slug категории',
				],
				'location' => [
					'type'        => 'integer',
					'description' => 'ID локации (rtcl_location)',
				],
				'location_slug' => [
					'type'        => 'string',
					'description' => 'Slug локации',
				],
				'listing_type' => [
					'type'        => 'string',
					'description' => 'Тип объявления',
					'enum'        => [ 'sell', 'buy', 'exchange', 'to_let', 'job' ],
				],
				'featured' => [
					'type'        => 'boolean',
					'description' => 'Только рекомендуемые (featured) объявления',
				],
				'search' => [
					'type'        => 'string',
					'description' => 'Поисковый запрос',
				],
				'min_price' => [
					'type'        => 'number',
					'description' => 'Минимальная цена',
				],
				'max_price' => [
					'type'        => 'number',
					'description' => 'Максимальная цена',
				],
				'author' => [
					'type'        => 'integer',
					'description' => 'ID автора объявлений',
				],
				'orderby' => [
					'type'        => 'string',
					'description' => 'Сортировка',
					'enum'        => [ 'date', 'title', 'modified', 'rand', 'meta_value_num', 'ID' ],
					'default'     => 'date',
				],
				'order' => [
					'type'        => 'string',
					'enum'        => [ 'ASC', 'DESC' ],
					'default'     => 'DESC',
				],
			],
		],
		'execute_callback'    => function ( array $input ): array {
			$per_page = min( absint( $input['per_page'] ?? 10 ), 100 );
			$page     = max( 1, absint( $input['page'] ?? 1 ) );

			$args = [
				'post_type'      => 'rtcl_listing',
				'post_status'    => sanitize_text_field( $input['status'] ?? 'publish' ),
				'posts_per_page' => $per_page,
				'paged'          => $page,
				'orderby'        => sanitize_text_field( $input['orderby'] ?? 'date' ),
				'order'          => sanitize_text_field( $input['order'] ?? 'DESC' ),
			];

			if ( ! empty( $input['search'] ) ) {
				$args['s'] = sanitize_text_field( $input['search'] );
			}
			if ( ! empty( $input['author'] ) ) {
				$args['author'] = absint( $input['author'] );
			}

			// Таксономические фильтры.
			$tax_query = [];
			if ( ! empty( $input['category'] ) ) {
				$tax_query[] = [ 'taxonomy' => 'rtcl_category', 'field' => 'term_id', 'terms' => absint( $input['category'] ) ];
			} elseif ( ! empty( $input['category_slug'] ) ) {
				$tax_query[] = [ 'taxonomy' => 'rtcl_category', 'field' => 'slug', 'terms' => sanitize_text_field( $input['category_slug'] ) ];
			}
			if ( ! empty( $input['location'] ) ) {
				$tax_query[] = [ 'taxonomy' => 'rtcl_location', 'field' => 'term_id', 'terms' => absint( $input['location'] ) ];
			} elseif ( ! empty( $input['location_slug'] ) ) {
				$tax_query[] = [ 'taxonomy' => 'rtcl_location', 'field' => 'slug', 'terms' => sanitize_text_field( $input['location_slug'] ) ];
			}
			if ( ! empty( $tax_query ) ) {
				$tax_query['relation'] = 'AND';
				$args['tax_query']     = $tax_query;
			}

			// Мета-фильтры.
			$meta_query = [];
			if ( ! empty( $input['listing_type'] ) ) {
				$meta_query[] = [ 'key' => '_rtcl_listing_type', 'value' => sanitize_text_field( $input['listing_type'] ) ];
			}
			if ( isset( $input['featured'] ) && $input['featured'] ) {
				$meta_query[] = [ 'key' => '_rtcl_featured', 'value' => '1' ];
			}
			if ( isset( $input['min_price'] ) || isset( $input['max_price'] ) ) {
				$price_meta = [ 'key' => '_rtcl_price', 'type' => 'NUMERIC' ];
				if ( isset( $input['min_price'] ) && isset( $input['max_price'] ) ) {
					$price_meta['value']   = [ floatval( $input['min_price'] ), floatval( $input['max_price'] ) ];
					$price_meta['compare'] = 'BETWEEN';
				} elseif ( isset( $input['min_price'] ) ) {
					$price_meta['value']   = floatval( $input['min_price'] );
					$price_meta['compare'] = '>=';
				} else {
					$price_meta['value']   = floatval( $input['max_price'] );
					$price_meta['compare'] = '<=';
				}
				$meta_query[] = $price_meta;
			}
			if ( ! empty( $meta_query ) ) {
				$meta_query['relation'] = 'AND';
				$args['meta_query']     = $meta_query;
			}

			$query = new WP_Query( $args );
			$items = [];

			foreach ( $query->posts as $post ) {
				$items[] = mcpap_format_rtcl_listing( $post );
			}

			return [
				'listings' => $items,
				'total'    => $query->found_posts,
				'pages'    => $query->max_num_pages,
				'page'     => $page,
				'per_page' => $per_page,
			];
		},
		'permission_callback' => function (): bool {
			return current_user_can( 'edit_posts' );
		},
	] );

	// --- Получить объявление по ID ---
	wp_register_ability( 'mcpap/rtcl-get-listing', [
		'label'       => __( 'Получить объявление по ID', 'mcp-abilities-provider' ),
		'description' => __( 'Возвращает полные данные объявления: контент, все мета-поля, категории, локации, кастомные поля, изображения, контактную информацию.', 'mcp-abilities-provider' ),
		'category'    => 'classified',
		'readonly'    => true,
		'meta'        => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		'input_schema' => [
			'type'       => 'object',
			'required'   => [ 'listing_id' ],
			'properties' => [
				'listing_id' => [ 'type' => 'integer', 'description' => 'ID объявления' ],
			],
		],
		'execute_callback'    => function ( array $input ): array|WP_Error {
			$post = get_post( absint( $input['listing_id'] ) );
			if ( ! $post || 'rtcl_listing' !== $post->post_type ) {
				return new WP_Error( 'not_found', 'Объявление не найдено' );
			}
			return mcpap_format_rtcl_listing( $post, true );
		},
		'permission_callback' => function (): bool {
			return current_user_can( 'edit_posts' );
		},
	] );

	// --- Создать объявление ---
	wp_register_ability( 'mcpap/rtcl-create-listing', [
		'label'       => __( 'Создать объявление', 'mcp-abilities-provider' ),
		'description' => __( 'Создаёт новое объявление с заголовком, описанием, ценой, типом, категориями, локациями и контактной информацией.', 'mcp-abilities-provider' ),
		'category'    => 'classified',
		'meta'        => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		'input_schema' => [
			'type'       => 'object',
			'required'   => [ 'title' ],
			'properties' => [
				'title'       => [ 'type' => 'string', 'description' => 'Заголовок объявления' ],
				'content'     => [ 'type' => 'string', 'description' => 'Описание объявления (HTML)' ],
				'excerpt'     => [ 'type' => 'string', 'description' => 'Краткое описание' ],
				'status'      => [ 'type' => 'string', 'enum' => [ 'publish', 'draft', 'pending' ], 'default' => 'pending' ],
				'category_ids' => [
					'type'  => 'array',
					'items' => [ 'type' => 'integer' ],
					'description' => 'ID категорий (rtcl_category)',
				],
				'location_ids' => [
					'type'  => 'array',
					'items' => [ 'type' => 'integer' ],
					'description' => 'ID локаций (rtcl_location)',
				],
				'listing_type' => [
					'type' => 'string',
					'enum' => [ 'sell', 'buy', 'exchange', 'to_let', 'job' ],
					'description' => 'Тип объявления',
				],
				'price'       => [ 'type' => 'number', 'description' => 'Цена' ],
				'max_price'   => [ 'type' => 'number', 'description' => 'Максимальная цена (для диапазона)' ],
				'price_type'  => [ 'type' => 'string', 'enum' => [ 'fixed', 'negotiable', 'on_call', 'free' ], 'default' => 'fixed' ],
				'phone'       => [ 'type' => 'string', 'description' => 'Контактный телефон' ],
				'whatsapp'    => [ 'type' => 'string', 'description' => 'WhatsApp номер' ],
				'email'       => [ 'type' => 'string', 'description' => 'Контактный email' ],
				'website'     => [ 'type' => 'string', 'description' => 'Сайт' ],
				'address'     => [ 'type' => 'string', 'description' => 'Адрес' ],
				'latitude'    => [ 'type' => 'number', 'description' => 'Широта' ],
				'longitude'   => [ 'type' => 'number', 'description' => 'Долгота' ],
				'zipcode'     => [ 'type' => 'string', 'description' => 'Почтовый индекс' ],
				'featured'    => [ 'type' => 'boolean', 'description' => 'Рекомендуемое объявление', 'default' => false ],
				'expiry_date' => [ 'type' => 'string', 'description' => 'Дата истечения (Y-m-d H:i:s)' ],
				'image_id'    => [ 'type' => 'integer', 'description' => 'ID миниатюры' ],
				'video_urls'  => [
					'type'  => 'array',
					'items' => [ 'type' => 'string' ],
					'description' => 'URL видео',
				],
				'custom_fields' => [
					'type'        => 'object',
					'description' => 'Кастомные поля: ключ = meta_key (с или без _rtcl_ префикса), значение = значение поля',
					'additionalProperties' => true,
				],
			],
		],
		'execute_callback'    => function ( array $input ): array|WP_Error {
			$post_data = [
				'post_type'    => 'rtcl_listing',
				'post_title'   => sanitize_text_field( $input['title'] ),
				'post_content' => wp_kses_post( $input['content'] ?? '' ),
				'post_excerpt' => sanitize_text_field( $input['excerpt'] ?? '' ),
				'post_status'  => sanitize_text_field( $input['status'] ?? 'pending' ),
				'post_author'  => get_current_user_id(),
			];

			$listing_id = wp_insert_post( $post_data, true );
			if ( is_wp_error( $listing_id ) ) {
				return $listing_id;
			}

			// Таксономии.
			if ( ! empty( $input['category_ids'] ) ) {
				wp_set_object_terms( $listing_id, array_map( 'absint', $input['category_ids'] ), 'rtcl_category' );
			}
			if ( ! empty( $input['location_ids'] ) ) {
				wp_set_object_terms( $listing_id, array_map( 'absint', $input['location_ids'] ), 'rtcl_location' );
			}

			// Мета-поля.
			mcpap_save_rtcl_listing_meta( $listing_id, $input );

			// Миниатюра.
			if ( ! empty( $input['image_id'] ) ) {
				set_post_thumbnail( $listing_id, absint( $input['image_id'] ) );
			}

			return [
				'listing_id' => $listing_id,
				'url'        => get_permalink( $listing_id ),
				'created'    => true,
			];
		},
		'permission_callback' => function (): bool {
			return current_user_can( 'publish_posts' );
		},
	] );

	// --- Обновить объявление ---
	wp_register_ability( 'mcpap/rtcl-update-listing', [
		'label'       => __( 'Обновить объявление', 'mcp-abilities-provider' ),
		'description' => __( 'Обновляет существующее объявление: заголовок, описание, цену, тип, контакты, адрес, категории, локации, кастомные поля.', 'mcp-abilities-provider' ),
		'category'    => 'classified',
		'meta'        => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		'input_schema' => [
			'type'       => 'object',
			'required'   => [ 'listing_id' ],
			'properties' => [
				'listing_id'   => [ 'type' => 'integer', 'description' => 'ID объявления' ],
				'title'        => [ 'type' => 'string', 'description' => 'Новый заголовок' ],
				'content'      => [ 'type' => 'string', 'description' => 'Новое описание (HTML)' ],
				'excerpt'      => [ 'type' => 'string', 'description' => 'Новое краткое описание' ],
				'status'       => [ 'type' => 'string', 'enum' => [ 'publish', 'draft', 'pending', 'private', 'expired' ] ],
				'slug'         => [ 'type' => 'string', 'description' => 'Новый slug' ],
				'category_ids' => [
					'type'  => 'array',
					'items' => [ 'type' => 'integer' ],
					'description' => 'Новые ID категорий (заменяет текущие)',
				],
				'location_ids' => [
					'type'  => 'array',
					'items' => [ 'type' => 'integer' ],
					'description' => 'Новые ID локаций (заменяет текущие)',
				],
				'listing_type' => [ 'type' => 'string', 'enum' => [ 'sell', 'buy', 'exchange', 'to_let', 'job' ] ],
				'price'        => [ 'type' => 'number', 'description' => 'Цена' ],
				'max_price'    => [ 'type' => 'number', 'description' => 'Максимальная цена' ],
				'price_type'   => [ 'type' => 'string', 'enum' => [ 'fixed', 'negotiable', 'on_call', 'free' ] ],
				'phone'        => [ 'type' => 'string' ],
				'whatsapp'     => [ 'type' => 'string' ],
				'email'        => [ 'type' => 'string' ],
				'website'      => [ 'type' => 'string' ],
				'address'      => [ 'type' => 'string' ],
				'latitude'     => [ 'type' => 'number' ],
				'longitude'    => [ 'type' => 'number' ],
				'zipcode'      => [ 'type' => 'string' ],
				'featured'     => [ 'type' => 'boolean' ],
				'expiry_date'  => [ 'type' => 'string', 'description' => 'Дата истечения (Y-m-d H:i:s)' ],
				'image_id'     => [ 'type' => 'integer', 'description' => 'ID миниатюры' ],
				'video_urls'   => [
					'type'  => 'array',
					'items' => [ 'type' => 'string' ],
				],
				'custom_fields' => [
					'type'        => 'object',
					'description' => 'Кастомные поля: ключ = meta_key, значение = значение поля',
					'additionalProperties' => true,
				],
			],
		],
		'execute_callback'    => function ( array $input ): array|WP_Error {
			$listing_id = absint( $input['listing_id'] );
			$post       = get_post( $listing_id );

			if ( ! $post || 'rtcl_listing' !== $post->post_type ) {
				return new WP_Error( 'not_found', 'Объявление не найдено' );
			}

			$post_data = [ 'ID' => $listing_id ];
			if ( isset( $input['title'] ) )   $post_data['post_title']   = sanitize_text_field( $input['title'] );
			if ( isset( $input['content'] ) ) $post_data['post_content'] = wp_kses_post( $input['content'] );
			if ( isset( $input['excerpt'] ) ) $post_data['post_excerpt'] = sanitize_text_field( $input['excerpt'] );
			if ( isset( $input['status'] ) )  $post_data['post_status']  = sanitize_text_field( $input['status'] );
			if ( isset( $input['slug'] ) )    $post_data['post_name']    = sanitize_title( $input['slug'] );

			$result = wp_update_post( $post_data, true );
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			// Таксономии.
			if ( isset( $input['category_ids'] ) ) {
				wp_set_object_terms( $listing_id, array_map( 'absint', $input['category_ids'] ), 'rtcl_category' );
			}
			if ( isset( $input['location_ids'] ) ) {
				wp_set_object_terms( $listing_id, array_map( 'absint', $input['location_ids'] ), 'rtcl_location' );
			}

			// Мета-поля.
			mcpap_save_rtcl_listing_meta( $listing_id, $input );

			// Миниатюра.
			if ( isset( $input['image_id'] ) ) {
				set_post_thumbnail( $listing_id, absint( $input['image_id'] ) );
			}

			return [
				'listing_id' => $listing_id,
				'url'        => get_permalink( $listing_id ),
				'updated'    => true,
			];
		},
		'permission_callback' => function (): bool {
			return current_user_can( 'edit_others_posts' );
		},
	] );

	// --- Удалить объявление ---
	wp_register_ability( 'mcpap/rtcl-delete-listing', [
		'label'       => __( 'Удалить объявление', 'mcp-abilities-provider' ),
		'description' => __( 'Перемещает объявление в корзину или удаляет окончательно.', 'mcp-abilities-provider' ),
		'category'    => 'classified',
		'meta'        => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		'input_schema' => [
			'type'       => 'object',
			'required'   => [ 'listing_id' ],
			'properties' => [
				'listing_id'   => [ 'type' => 'integer', 'description' => 'ID объявления' ],
				'force_delete' => [ 'type' => 'boolean', 'default' => false, 'description' => 'true — удалить навсегда, false — в корзину' ],
			],
		],
		'execute_callback'    => function ( array $input ): array|WP_Error {
			$post = get_post( absint( $input['listing_id'] ) );
			if ( ! $post || 'rtcl_listing' !== $post->post_type ) {
				return new WP_Error( 'not_found', 'Объявление не найдено' );
			}

			$force  = (bool) ( $input['force_delete'] ?? false );
			$result = wp_delete_post( $post->ID, $force );
			if ( ! $result ) {
				return new WP_Error( 'delete_failed', 'Ошибка удаления' );
			}

			return [ 'listing_id' => $post->ID, 'deleted' => true, 'trashed' => ! $force ];
		},
		'permission_callback' => function (): bool {
			return current_user_can( 'delete_others_posts' );
		},
	] );
}

// ═════════════════════════════════════════════════════════════════════════════
// CLASSIFIED LISTING: ТАКСОНОМИИ (категории, локации, типы)
// ═════════════════════════════════════════════════════════════════════════════

/**
 * Регистрирует abilities для таксономий Classified Listing.
 *
 * @since 1.2.0
 */
function mcpap_register_rtcl_taxonomy_abilities(): void {

	// --- Категории объявлений ---
	wp_register_ability( 'mcpap/rtcl-get-categories', [
		'label'       => __( 'Получить категории объявлений', 'mcp-abilities-provider' ),
		'description' => __( 'Возвращает иерархическое дерево категорий Classified Listing с количеством объявлений, иконками и описаниями.', 'mcp-abilities-provider' ),
		'category'    => 'classified',
		'readonly'    => true,
		'meta'        => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		'input_schema' => [
			'type'       => 'object',
			'properties' => [
				'hide_empty' => [ 'type' => 'boolean', 'default' => false, 'description' => 'Скрыть пустые категории' ],
				'parent'     => [ 'type' => 'integer', 'description' => 'ID родителя (0 — только верхний уровень)' ],
				'search'     => [ 'type' => 'string', 'description' => 'Поиск по названию' ],
			],
		],
		'execute_callback'    => function ( array $input ): array {
			$args = [
				'taxonomy'   => 'rtcl_category',
				'hide_empty' => (bool) ( $input['hide_empty'] ?? false ),
				'orderby'    => 'name',
			];
			if ( isset( $input['parent'] ) ) {
				$args['parent'] = absint( $input['parent'] );
			}
			if ( ! empty( $input['search'] ) ) {
				$args['search'] = sanitize_text_field( $input['search'] );
			}

			$terms  = get_terms( $args );
			$result = [];

			if ( is_wp_error( $terms ) ) {
				return [];
			}

			foreach ( $terms as $term ) {
				$item = [
					'term_id'     => $term->term_id,
					'name'        => $term->name,
					'slug'        => $term->slug,
					'description' => $term->description,
					'parent'      => $term->parent,
					'count'       => $term->count,
				];

				// Иконка категории (CL хранит в term_meta).
				$icon = get_term_meta( $term->term_id, '_rtcl_icon', true );
				if ( $icon ) {
					$item['icon'] = $icon;
				}
				$image = get_term_meta( $term->term_id, '_rtcl_image', true );
				if ( $image ) {
					$item['image_id']  = $image;
					$item['image_url'] = wp_get_attachment_url( $image );
				}

				$result[] = $item;
			}

			return $result;
		},
		'permission_callback' => function (): bool {
			return current_user_can( 'edit_posts' );
		},
	] );

	// --- Создать категорию ---
	wp_register_ability( 'mcpap/rtcl-create-category', [
		'label'       => __( 'Создать категорию объявлений', 'mcp-abilities-provider' ),
		'description' => __( 'Создаёт новую категорию в таксономии rtcl_category.', 'mcp-abilities-provider' ),
		'category'    => 'classified',
		'meta'        => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		'input_schema' => [
			'type'       => 'object',
			'required'   => [ 'name' ],
			'properties' => [
				'name'        => [ 'type' => 'string', 'description' => 'Название категории' ],
				'slug'        => [ 'type' => 'string', 'description' => 'Slug (ЧПУ)' ],
				'description' => [ 'type' => 'string', 'description' => 'Описание' ],
				'parent'      => [ 'type' => 'integer', 'description' => 'ID родительской категории', 'default' => 0 ],
				'icon'        => [ 'type' => 'string', 'description' => 'CSS-класс иконки (например, rtcl-icon-car)' ],
				'image_id'    => [ 'type' => 'integer', 'description' => 'ID изображения категории' ],
			],
		],
		'execute_callback'    => function ( array $input ): array|WP_Error {
			$args = [];
			if ( ! empty( $input['slug'] ) )        $args['slug']        = sanitize_title( $input['slug'] );
			if ( ! empty( $input['description'] ) )  $args['description'] = sanitize_text_field( $input['description'] );
			if ( isset( $input['parent'] ) )         $args['parent']      = absint( $input['parent'] );

			$term = wp_insert_term( sanitize_text_field( $input['name'] ), 'rtcl_category', $args );
			if ( is_wp_error( $term ) ) {
				return $term;
			}

			if ( ! empty( $input['icon'] ) ) {
				update_term_meta( $term['term_id'], '_rtcl_icon', sanitize_text_field( $input['icon'] ) );
			}
			if ( ! empty( $input['image_id'] ) ) {
				update_term_meta( $term['term_id'], '_rtcl_image', absint( $input['image_id'] ) );
			}

			return [
				'term_id' => $term['term_id'],
				'name'    => sanitize_text_field( $input['name'] ),
				'created' => true,
			];
		},
		'permission_callback' => function (): bool {
			return current_user_can( 'manage_categories' );
		},
	] );

	// --- Локации ---
	wp_register_ability( 'mcpap/rtcl-get-locations', [
		'label'       => __( 'Получить локации', 'mcp-abilities-provider' ),
		'description' => __( 'Возвращает иерархическое дерево локаций Classified Listing (страна > регион > город).', 'mcp-abilities-provider' ),
		'category'    => 'classified',
		'readonly'    => true,
		'meta'        => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		'input_schema' => [
			'type'       => 'object',
			'properties' => [
				'hide_empty' => [ 'type' => 'boolean', 'default' => false ],
				'parent'     => [ 'type' => 'integer', 'description' => 'ID родителя (0 — только верхний уровень)' ],
				'search'     => [ 'type' => 'string', 'description' => 'Поиск по названию' ],
			],
		],
		'execute_callback'    => function ( array $input ): array {
			$args = [
				'taxonomy'   => 'rtcl_location',
				'hide_empty' => (bool) ( $input['hide_empty'] ?? false ),
				'orderby'    => 'name',
			];
			if ( isset( $input['parent'] ) ) {
				$args['parent'] = absint( $input['parent'] );
			}
			if ( ! empty( $input['search'] ) ) {
				$args['search'] = sanitize_text_field( $input['search'] );
			}

			$terms  = get_terms( $args );
			$result = [];

			if ( is_wp_error( $terms ) ) {
				return [];
			}

			foreach ( $terms as $term ) {
				$item = [
					'term_id'     => $term->term_id,
					'name'        => $term->name,
					'slug'        => $term->slug,
					'description' => $term->description,
					'parent'      => $term->parent,
					'count'       => $term->count,
				];

				// Координаты локации.
				$lat = get_term_meta( $term->term_id, '_rtcl_latitude', true );
				$lng = get_term_meta( $term->term_id, '_rtcl_longitude', true );
				if ( $lat ) $item['latitude']  = (float) $lat;
				if ( $lng ) $item['longitude'] = (float) $lng;

				$result[] = $item;
			}

			return $result;
		},
		'permission_callback' => function (): bool {
			return current_user_can( 'edit_posts' );
		},
	] );

	// --- Создать локацию ---
	wp_register_ability( 'mcpap/rtcl-create-location', [
		'label'       => __( 'Создать локацию', 'mcp-abilities-provider' ),
		'description' => __( 'Создаёт новую локацию в таксономии rtcl_location.', 'mcp-abilities-provider' ),
		'category'    => 'classified',
		'meta'        => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		'input_schema' => [
			'type'       => 'object',
			'required'   => [ 'name' ],
			'properties' => [
				'name'        => [ 'type' => 'string', 'description' => 'Название локации' ],
				'slug'        => [ 'type' => 'string', 'description' => 'Slug (ЧПУ)' ],
				'description' => [ 'type' => 'string', 'description' => 'Описание' ],
				'parent'      => [ 'type' => 'integer', 'description' => 'ID родительской локации', 'default' => 0 ],
				'latitude'    => [ 'type' => 'number', 'description' => 'Широта' ],
				'longitude'   => [ 'type' => 'number', 'description' => 'Долгота' ],
			],
		],
		'execute_callback'    => function ( array $input ): array|WP_Error {
			$args = [];
			if ( ! empty( $input['slug'] ) )        $args['slug']        = sanitize_title( $input['slug'] );
			if ( ! empty( $input['description'] ) )  $args['description'] = sanitize_text_field( $input['description'] );
			if ( isset( $input['parent'] ) )         $args['parent']      = absint( $input['parent'] );

			$term = wp_insert_term( sanitize_text_field( $input['name'] ), 'rtcl_location', $args );
			if ( is_wp_error( $term ) ) {
				return $term;
			}

			if ( isset( $input['latitude'] ) ) {
				update_term_meta( $term['term_id'], '_rtcl_latitude', sanitize_text_field( $input['latitude'] ) );
			}
			if ( isset( $input['longitude'] ) ) {
				update_term_meta( $term['term_id'], '_rtcl_longitude', sanitize_text_field( $input['longitude'] ) );
			}

			return [
				'term_id' => $term['term_id'],
				'name'    => sanitize_text_field( $input['name'] ),
				'created' => true,
			];
		},
		'permission_callback' => function (): bool {
			return current_user_can( 'manage_categories' );
		},
	] );

	// --- Типы объявлений ---
	wp_register_ability( 'mcpap/rtcl-get-listing-types', [
		'label'       => __( 'Получить типы объявлений', 'mcp-abilities-provider' ),
		'description' => __( 'Возвращает доступные типы объявлений (sell, buy, exchange, to_let, job и кастомные).', 'mcp-abilities-provider' ),
		'category'    => 'classified',
		'readonly'    => true,
		'meta'        => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		'input_schema' => [
			'type'       => 'object',
			'properties' => [],
		],
		'execute_callback'    => function ( array $input ): array {
			// Проверяем зарегистрирована ли таксономия rtcl_listing_type.
			if ( taxonomy_exists( 'rtcl_listing_type' ) ) {
				$terms = get_terms( [
					'taxonomy'   => 'rtcl_listing_type',
					'hide_empty' => false,
				] );
				if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
					$result = [];
					foreach ( $terms as $term ) {
						$result[] = [
							'term_id' => $term->term_id,
							'name'    => $term->name,
							'slug'    => $term->slug,
							'count'   => $term->count,
						];
					}
					return $result;
				}
			}

			// Fallback: стандартные типы из настроек CL.
			$types = apply_filters( 'rtcl_listing_types', [
				'sell'     => __( 'Продажа', 'mcp-abilities-provider' ),
				'buy'      => __( 'Покупка', 'mcp-abilities-provider' ),
				'exchange' => __( 'Обмен', 'mcp-abilities-provider' ),
				'to_let'   => __( 'Аренда', 'mcp-abilities-provider' ),
			] );

			$result = [];
			foreach ( $types as $slug => $label ) {
				$result[] = [ 'slug' => $slug, 'name' => $label ];
			}
			return $result;
		},
		'permission_callback' => function (): bool {
			return current_user_can( 'edit_posts' );
		},
	] );
}

// ═════════════════════════════════════════════════════════════════════════════
// CLASSIFIED LISTING: НАСТРОЙКИ И FORM BUILDER
// ═════════════════════════════════════════════════════════════════════════════

/**
 * Регистрирует abilities для конфигурации Classified Listing.
 *
 * @since 1.2.0
 */
function mcpap_register_rtcl_config_abilities(): void {

	// --- Настройки CL ---
	wp_register_ability( 'mcpap/rtcl-get-settings', [
		'label'       => __( 'Получить настройки Classified Listing', 'mcp-abilities-provider' ),
		'description' => __( 'Возвращает основные настройки плагина: модерация, лимиты, страницы, карта, валюта, reCAPTCHA.', 'mcp-abilities-provider' ),
		'category'    => 'classified',
		'readonly'    => true,
		'meta'        => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		'input_schema' => [
			'type'       => 'object',
			'properties' => [
				'group' => [
					'type'        => 'string',
					'description' => 'Группа настроек (all — все)',
					'enum'        => [ 'all', 'general', 'moderation', 'page', 'currency', 'map', 'recaptcha' ],
					'default'     => 'all',
				],
			],
		],
		'execute_callback'    => function ( array $input ): array {
			$group  = sanitize_text_field( $input['group'] ?? 'all' );
			$result = [];

			// General / Moderation settings.
			if ( in_array( $group, [ 'all', 'general', 'moderation' ], true ) ) {
				$general = get_option( 'rtcl_general_settings', [] );
				$result['general'] = [
					'new_listing_status'    => $general['new_listing_status'] ?? 'pending',
					'edited_listing_status' => $general['edited_listing_status'] ?? 'pending',
					'listing_duration'      => $general['listing_duration'] ?? 30,
					'max_image_limit'       => $general['max_image_limit'] ?? 5,
					'max_image_size'        => $general['image_allowed_memory'] ?? 2,
					'has_map'               => ! empty( $general['has_map'] ) ? true : false,
					'has_price'             => ! empty( $general['has_price'] ) ? true : false,
					'has_category'          => ! empty( $general['has_category'] ) ? true : false,
				];
			}

			// Page settings.
			if ( in_array( $group, [ 'all', 'page' ], true ) ) {
				$pages = get_option( 'rtcl_advanced_settings', [] );
				$page_ids = [
					'listings'  => $pages['listings_page'] ?? 0,
					'my_account' => $pages['myaccount_page'] ?? 0,
					'checkout'  => $pages['checkout_page'] ?? 0,
					'submission' => $pages['submission_form_page'] ?? 0,
				];
				$result['pages'] = [];
				foreach ( $page_ids as $key => $pid ) {
					$result['pages'][ $key ] = [
						'page_id' => (int) $pid,
						'url'     => $pid ? get_permalink( $pid ) : '',
						'title'   => $pid ? get_the_title( $pid ) : '',
					];
				}
			}

			// Currency.
			if ( in_array( $group, [ 'all', 'currency' ], true ) ) {
				$payment = get_option( 'rtcl_payment_settings', [] );
				$result['currency'] = [
					'currency'          => $payment['currency'] ?? 'USD',
					'currency_position' => $payment['currency_position'] ?? 'left',
					'thousand_separator' => $payment['thousand_separator'] ?? ',',
					'decimal_separator'  => $payment['decimal_separator'] ?? '.',
				];
			}

			// Map.
			if ( in_array( $group, [ 'all', 'map' ], true ) ) {
				$misc = get_option( 'rtcl_misc_settings', [] );
				$result['map'] = [
					'map_type'    => $misc['map_type'] ?? 'google',
					'has_map_key' => ! empty( $misc['map_api_key'] ),
					'zoom_level'  => $misc['map_zoom_level'] ?? 15,
					'center_lat'  => $misc['default_latitude'] ?? '',
					'center_lng'  => $misc['default_longitude'] ?? '',
				];
			}

			// reCAPTCHA.
			if ( in_array( $group, [ 'all', 'recaptcha' ], true ) ) {
				$result['recaptcha'] = [
					'enabled'      => ! empty( get_option( 'rtcl_misc_settings', [] )['recaptcha_site_key'] ),
					'version'      => get_option( 'rtcl_misc_settings', [] )['recaptcha_version'] ?? 'v2',
				];
			}

			$result['version'] = defined( 'RTCL_VERSION' ) ? RTCL_VERSION : 'unknown';
			$result['pro']     = defined( 'RTCL_PRO_VERSION' );

			return $result;
		},
		'permission_callback' => function (): bool {
			return current_user_can( 'manage_options' );
		},
	] );

	// --- Тарифные планы / Pricing ---
	wp_register_ability( 'mcpap/rtcl-get-pricing-plans', [
		'label'       => __( 'Получить тарифные планы', 'mcp-abilities-provider' ),
		'description' => __( 'Возвращает список тарифных планов (pricing/membership) Classified Listing с ценами, лимитами и параметрами.', 'mcp-abilities-provider' ),
		'category'    => 'classified',
		'readonly'    => true,
		'meta'        => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		'input_schema' => [
			'type'       => 'object',
			'properties' => [
				'status' => [
					'type'    => 'string',
					'enum'    => [ 'publish', 'draft', 'any' ],
					'default' => 'publish',
				],
			],
		],
		'execute_callback'    => function ( array $input ): array {
			// Pricing plans — CPT 'rtcl_pricing'.
			$pricing_type = post_type_exists( 'rtcl_pricing' ) ? 'rtcl_pricing' : null;

			if ( ! $pricing_type ) {
				return [ 'message' => 'Pricing plans не активированы' ];
			}

			$plans = get_posts( [
				'post_type'   => $pricing_type,
				'post_status' => sanitize_text_field( $input['status'] ?? 'publish' ),
				'numberposts' => 50,
				'orderby'     => 'menu_order',
				'order'       => 'ASC',
			] );

			$result = [];
			foreach ( $plans as $plan ) {
				$item = [
					'plan_id'     => $plan->ID,
					'title'       => $plan->post_title,
					'description' => $plan->post_content,
					'status'      => $plan->post_status,
				];

				// Мета-поля плана.
				$meta_keys = [
					'price', 'visible', 'featured', 'regular_ads', 'promotion_bump_up',
					'promotion_featured', 'promotion_top', 'duration',
				];
				foreach ( $meta_keys as $key ) {
					$val = get_post_meta( $plan->ID, $key, true );
					if ( '' !== $val ) {
						$item[ $key ] = $val;
					}
				}

				$result[] = $item;
			}

			return $result;
		},
		'permission_callback' => function (): bool {
			return current_user_can( 'manage_options' );
		},
	] );

	// --- Получить кастомные поля формы ---
	wp_register_ability( 'mcpap/rtcl-get-form-fields', [
		'label'       => __( 'Получить поля формы объявления', 'mcp-abilities-provider' ),
		'description' => __( 'Возвращает кастомные поля (Form Builder) для указанной категории. Полезно для понимания структуры данных перед созданием/обновлением объявлений.', 'mcp-abilities-provider' ),
		'category'    => 'classified',
		'readonly'    => true,
		'meta'        => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		'input_schema' => [
			'type'       => 'object',
			'properties' => [
				'category_id' => [
					'type'        => 'integer',
					'description' => 'ID категории для получения привязанных полей (если не указан — возвращает все custom field groups)',
				],
			],
		],
		'execute_callback'    => function ( array $input ): array {
			// Custom Field Groups — CPT 'rtcl_cfg'.
			$cfg_type = post_type_exists( 'rtcl_cfg' ) ? 'rtcl_cfg' : null;

			if ( ! $cfg_type ) {
				return [ 'message' => 'Form Builder не найден (rtcl_cfg)' ];
			}

			$args = [
				'post_type'   => $cfg_type,
				'post_status' => 'publish',
				'numberposts' => 100,
			];

			$groups = get_posts( $args );
			$result = [];

			foreach ( $groups as $group ) {
				$group_data = [
					'group_id'    => $group->ID,
					'title'       => $group->post_title,
					'categories'  => get_post_meta( $group->ID, '_rtcl_cfg_categories', true ) ?: [],
				];

				// Если запрошена конкретная категория — фильтруем.
				if ( ! empty( $input['category_id'] ) ) {
					$cats = $group_data['categories'];
					if ( ! empty( $cats ) && ! in_array( absint( $input['category_id'] ), array_map( 'absint', (array) $cats ), true ) ) {
						continue; // Группа не привязана к этой категории.
					}
				}

				// Получаем поля группы (CPT 'rtcl_cf').
				$fields = get_posts( [
					'post_type'   => 'rtcl_cf',
					'post_parent' => $group->ID,
					'post_status' => 'publish',
					'numberposts' => 100,
					'orderby'     => 'menu_order',
					'order'       => 'ASC',
				] );

				$group_data['fields'] = [];
				foreach ( $fields as $field ) {
					$field_data = [
						'field_id'    => $field->ID,
						'label'       => $field->post_title,
						'meta_key'    => get_post_meta( $field->ID, '_meta_key', true ) ?: '',
						'type'        => get_post_meta( $field->ID, '_type', true ) ?: 'text',
						'required'    => (bool) get_post_meta( $field->ID, '_required', true ),
						'placeholder' => get_post_meta( $field->ID, '_placeholder', true ) ?: '',
						'description' => get_post_meta( $field->ID, '_description', true ) ?: '',
					];

					// Опции для select/radio/checkbox.
					$options = get_post_meta( $field->ID, '_options', true );
					if ( $options ) {
						$field_data['options'] = $options;
					}

					// Validation.
					$min = get_post_meta( $field->ID, '_min', true );
					$max = get_post_meta( $field->ID, '_max', true );
					if ( '' !== $min ) $field_data['min'] = $min;
					if ( '' !== $max ) $field_data['max'] = $max;

					$group_data['fields'][] = $field_data;
				}

				$result[] = $group_data;
			}

			return $result;
		},
		'permission_callback' => function (): bool {
			return current_user_can( 'edit_posts' );
		},
	] );
}

// ═════════════════════════════════════════════════════════════════════════════
// CLASSIFIED LISTING: STORE & MEMBERSHIP
// ═════════════════════════════════════════════════════════════════════════════

/**
 * Регистрирует abilities для магазинов (Store Addon).
 *
 * @since 1.2.0
 */
function mcpap_register_rtcl_store_abilities(): void {

	// --- Список магазинов ---
	wp_register_ability( 'mcpap/rtcl-get-stores', [
		'label'       => __( 'Получить магазины', 'mcp-abilities-provider' ),
		'description' => __( 'Список магазинов продавцов (Store Addon) с информацией о владельце, рейтинге и количестве объявлений.', 'mcp-abilities-provider' ),
		'category'    => 'classified',
		'readonly'    => true,
		'meta'        => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		'input_schema' => [
			'type'       => 'object',
			'properties' => [
				'per_page' => [ 'type' => 'integer', 'default' => 20, 'maximum' => 100 ],
				'page'     => [ 'type' => 'integer', 'default' => 1 ],
				'search'   => [ 'type' => 'string', 'description' => 'Поиск по названию магазина' ],
				'orderby'  => [
					'type' => 'string',
					'enum' => [ 'date', 'title', 'modified' ],
					'default' => 'date',
				],
			],
		],
		'execute_callback'    => function ( array $input ): array {
			$store_cpt = post_type_exists( 'store' ) ? 'store' : ( post_type_exists( 'rtcl_store' ) ? 'rtcl_store' : null );

			if ( ! $store_cpt ) {
				return [ 'message' => 'Store Addon не активирован' ];
			}

			$args = [
				'post_type'      => $store_cpt,
				'post_status'    => 'publish',
				'posts_per_page' => min( absint( $input['per_page'] ?? 20 ), 100 ),
				'paged'          => max( 1, absint( $input['page'] ?? 1 ) ),
				'orderby'        => sanitize_text_field( $input['orderby'] ?? 'date' ),
				'order'          => 'DESC',
			];

			if ( ! empty( $input['search'] ) ) {
				$args['s'] = sanitize_text_field( $input['search'] );
			}

			$query = new WP_Query( $args );
			$items = [];

			foreach ( $query->posts as $store ) {
				$items[] = mcpap_format_rtcl_store( $store );
			}

			return [
				'stores' => $items,
				'total'  => $query->found_posts,
				'pages'  => $query->max_num_pages,
			];
		},
		'permission_callback' => function (): bool {
			return current_user_can( 'edit_posts' );
		},
	] );

	// --- Получить магазин по ID ---
	wp_register_ability( 'mcpap/rtcl-get-store', [
		'label'       => __( 'Получить магазин по ID', 'mcp-abilities-provider' ),
		'description' => __( 'Полные данные магазина: описание, контакты, соцсети, время работы, баннер, рейтинг, количество объявлений.', 'mcp-abilities-provider' ),
		'category'    => 'classified',
		'readonly'    => true,
		'meta'        => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		'input_schema' => [
			'type'       => 'object',
			'required'   => [ 'store_id' ],
			'properties' => [
				'store_id' => [ 'type' => 'integer', 'description' => 'ID магазина' ],
			],
		],
		'execute_callback'    => function ( array $input ): array|WP_Error {
			$store_cpt = post_type_exists( 'store' ) ? 'store' : ( post_type_exists( 'rtcl_store' ) ? 'rtcl_store' : null );

			if ( ! $store_cpt ) {
				return new WP_Error( 'addon_missing', 'Store Addon не активирован' );
			}

			$store = get_post( absint( $input['store_id'] ) );
			if ( ! $store || $store->post_type !== $store_cpt ) {
				return new WP_Error( 'not_found', 'Магазин не найден' );
			}

			return mcpap_format_rtcl_store( $store, true );
		},
		'permission_callback' => function (): bool {
			return current_user_can( 'edit_posts' );
		},
	] );

	// --- Получить Membership Plans ---
	wp_register_ability( 'mcpap/rtcl-get-membership-plans', [
		'label'       => __( 'Получить планы членства', 'mcp-abilities-provider' ),
		'description' => __( 'Возвращает доступные Membership Plans (Store Addon) с ценами, лимитами и включёнными возможностями.', 'mcp-abilities-provider' ),
		'category'    => 'classified',
		'readonly'    => true,
		'meta'        => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		'input_schema' => [
			'type'       => 'object',
			'properties' => [
				'status' => [ 'type' => 'string', 'enum' => [ 'publish', 'draft', 'any' ], 'default' => 'publish' ],
			],
		],
		'execute_callback'    => function ( array $input ): array {
			// Membership — хранится в rtcl_pricing или отдельном CPT.
			$cpt = null;
			foreach ( [ 'rtcl_membership', 'rtcl_pricing' ] as $type ) {
				if ( post_type_exists( $type ) ) {
					$cpt = $type;
					break;
				}
			}

			if ( ! $cpt ) {
				return [ 'message' => 'Membership Plans не найдены' ];
			}

			$plans = get_posts( [
				'post_type'   => $cpt,
				'post_status' => sanitize_text_field( $input['status'] ?? 'publish' ),
				'numberposts' => 50,
				'orderby'     => 'menu_order',
				'order'       => 'ASC',
			] );

			$result = [];
			foreach ( $plans as $plan ) {
				$item = [
					'plan_id'     => $plan->ID,
					'title'       => $plan->post_title,
					'description' => $plan->post_content,
					'status'      => $plan->post_status,
				];

				// Все мета-поля плана.
				$all_meta = get_post_meta( $plan->ID );
				foreach ( $all_meta as $key => $values ) {
					if ( str_starts_with( $key, '_' ) && ! str_starts_with( $key, '_edit_' ) ) {
						continue;
					}
					$item[ $key ] = count( $values ) === 1 ? $values[0] : $values;
				}

				// Ключевые поля отдельно.
				$meta_keys = [ 'price', 'visible', 'featured', 'regular_ads', 'duration' ];
				foreach ( $meta_keys as $key ) {
					$val = get_post_meta( $plan->ID, $key, true );
					if ( '' !== $val ) {
						$item[ $key ] = $val;
					}
				}

				$result[] = $item;
			}

			return $result;
		},
		'permission_callback' => function (): bool {
			return current_user_can( 'manage_options' );
		},
	] );
}

// ═════════════════════════════════════════════════════════════════════════════
// CLASSIFIED LISTING: HELPER FUNCTIONS
// ═════════════════════════════════════════════════════════════════════════════

/**
 * Сохраняет мета-поля объявления Classified Listing.
 *
 * @since 1.2.0
 *
 * @param int   $listing_id ID объявления.
 * @param array $input      Входные данные.
 */
function mcpap_save_rtcl_listing_meta( int $listing_id, array $input ): void {
	$meta_map = [
		'listing_type' => '_rtcl_listing_type',
		'price'        => '_rtcl_price',
		'max_price'    => '_rtcl_max_price',
		'price_type'   => '_rtcl_price_type',
		'phone'        => '_rtcl_phone',
		'whatsapp'     => '_rtcl_whatsapp_number',
		'email'        => '_rtcl_email',
		'website'      => '_rtcl_website',
		'address'      => '_rtcl_address',
		'latitude'     => '_rtcl_latitude',
		'longitude'    => '_rtcl_longitude',
		'zipcode'      => '_rtcl_zipcode',
		'expiry_date'  => '_rtcl_expiry_date',
	];

	foreach ( $meta_map as $input_key => $meta_key ) {
		if ( isset( $input[ $input_key ] ) ) {
			update_post_meta( $listing_id, $meta_key, sanitize_text_field( $input[ $input_key ] ) );
		}
	}

	// Featured.
	if ( isset( $input['featured'] ) ) {
		update_post_meta( $listing_id, '_rtcl_featured', $input['featured'] ? 1 : 0 );
	}

	// Видео.
	if ( isset( $input['video_urls'] ) ) {
		$urls = array_map( 'esc_url_raw', (array) $input['video_urls'] );
		update_post_meta( $listing_id, '_rtcl_video_urls', $urls );
	}

	// Кастомные поля (Form Builder).
	if ( ! empty( $input['custom_fields'] ) && is_array( $input['custom_fields'] ) ) {
		foreach ( $input['custom_fields'] as $key => $value ) {
			// Если ключ не начинается с _ — добавляем _rtcl_ префикс.
			$meta_key = str_starts_with( $key, '_' ) ? $key : '_rtcl_' . $key;
			if ( is_array( $value ) ) {
				update_post_meta( $listing_id, sanitize_text_field( $meta_key ), array_map( 'sanitize_text_field', $value ) );
			} else {
				update_post_meta( $listing_id, sanitize_text_field( $meta_key ), sanitize_text_field( $value ) );
			}
		}
	}
}

/**
 * Форматирует данные объявления Classified Listing.
 *
 * @since 1.2.0
 *
 * @param WP_Post $post Объект записи.
 * @param bool    $full Полный режим (все мета, кастомные поля, галерея).
 * @return array
 */
function mcpap_format_rtcl_listing( WP_Post $post, bool $full = false ): array {
	$data = [
		'listing_id'   => $post->ID,
		'title'        => $post->post_title,
		'slug'         => $post->post_name,
		'status'       => $post->post_status,
		'url'          => get_permalink( $post->ID ),
		'date'         => $post->post_date,
		'modified'     => $post->post_modified,
		'author_id'    => (int) $post->post_author,
	];

	// Основные мета.
	$data['price']        = get_post_meta( $post->ID, '_rtcl_price', true ) ?: null;
	$data['max_price']    = get_post_meta( $post->ID, '_rtcl_max_price', true ) ?: null;
	$data['price_type']   = get_post_meta( $post->ID, '_rtcl_price_type', true ) ?: 'fixed';
	$data['listing_type'] = get_post_meta( $post->ID, '_rtcl_listing_type', true ) ?: null;
	$data['featured']     = (bool) get_post_meta( $post->ID, '_rtcl_featured', true );
	$data['views']        = (int) get_post_meta( $post->ID, '_rtcl_views', true );
	$data['expiry_date']  = get_post_meta( $post->ID, '_rtcl_expiry_date', true ) ?: null;

	// Категории и локации.
	$cats = wp_get_object_terms( $post->ID, 'rtcl_category', [ 'fields' => 'all' ] );
	$data['categories'] = [];
	if ( ! is_wp_error( $cats ) ) {
		foreach ( $cats as $cat ) {
			$data['categories'][] = [ 'term_id' => $cat->term_id, 'name' => $cat->name, 'slug' => $cat->slug ];
		}
	}

	$locs = wp_get_object_terms( $post->ID, 'rtcl_location', [ 'fields' => 'all' ] );
	$data['locations'] = [];
	if ( ! is_wp_error( $locs ) ) {
		foreach ( $locs as $loc ) {
			$data['locations'][] = [ 'term_id' => $loc->term_id, 'name' => $loc->name, 'slug' => $loc->slug ];
		}
	}

	// Миниатюра.
	$thumb_id = get_post_thumbnail_id( $post->ID );
	if ( $thumb_id ) {
		$data['thumbnail'] = [
			'id'  => $thumb_id,
			'url' => wp_get_attachment_url( $thumb_id ),
		];
	}

	if ( $full ) {
		// Контент.
		$data['content'] = $post->post_content;
		$data['excerpt'] = $post->post_excerpt;

		// Контактная информация.
		$data['contact'] = [
			'phone'    => get_post_meta( $post->ID, '_rtcl_phone', true ) ?: null,
			'whatsapp' => get_post_meta( $post->ID, '_rtcl_whatsapp_number', true ) ?: null,
			'email'    => get_post_meta( $post->ID, '_rtcl_email', true ) ?: null,
			'website'  => get_post_meta( $post->ID, '_rtcl_website', true ) ?: null,
		];

		// Геолокация.
		$data['geo'] = [
			'address'   => get_post_meta( $post->ID, '_rtcl_address', true ) ?: null,
			'geo_address' => get_post_meta( $post->ID, '_rtcl_geo_address', true ) ?: null,
			'latitude'  => get_post_meta( $post->ID, '_rtcl_latitude', true ) ?: null,
			'longitude' => get_post_meta( $post->ID, '_rtcl_longitude', true ) ?: null,
			'zipcode'   => get_post_meta( $post->ID, '_rtcl_zipcode', true ) ?: null,
		];

		// Видео.
		$videos = get_post_meta( $post->ID, '_rtcl_video_urls', true );
		if ( $videos ) {
			$data['video_urls'] = $videos;
		}

		// Галерея изображений.
		$gallery_ids = get_post_meta( $post->ID, '_rtcl_images', true );
		if ( $gallery_ids && is_array( $gallery_ids ) ) {
			$data['gallery'] = [];
			foreach ( $gallery_ids as $img_id ) {
				$data['gallery'][] = [
					'id'  => $img_id,
					'url' => wp_get_attachment_url( $img_id ),
				];
			}
		}

		// Все кастомные поля (_rtcl_ и Form Builder).
		$all_meta = get_post_meta( $post->ID );
		$data['custom_fields'] = [];
		$skip_keys = [
			'_rtcl_price', '_rtcl_max_price', '_rtcl_price_type', '_rtcl_listing_type',
			'_rtcl_featured', '_rtcl_views', '_rtcl_expiry_date',
			'_rtcl_phone', '_rtcl_whatsapp_number', '_rtcl_email', '_rtcl_website',
			'_rtcl_address', '_rtcl_geo_address', '_rtcl_latitude', '_rtcl_longitude', '_rtcl_zipcode',
			'_rtcl_video_urls', '_rtcl_images', '_rtcl_manager_id',
			'_thumbnail_id', '_edit_lock', '_edit_last', '_wp_old_slug',
		];

		foreach ( $all_meta as $key => $values ) {
			if ( in_array( $key, $skip_keys, true ) ) {
				continue;
			}
			// Включаем _rtcl_ кастомные поля и поля без подчёркивания (Form Builder).
			if ( str_starts_with( $key, '_rtcl_' ) || ! str_starts_with( $key, '_' ) ) {
				$val = count( $values ) === 1 ? maybe_unserialize( $values[0] ) : array_map( 'maybe_unserialize', $values );
				if ( '' !== $val && null !== $val ) {
					$data['custom_fields'][ $key ] = $val;
				}
			}
		}

		// Автор.
		$author = get_user_by( 'ID', $post->post_author );
		if ( $author ) {
			$data['author'] = [
				'id'           => $author->ID,
				'display_name' => $author->display_name,
				'email'        => $author->user_email,
			];
		}

		$data['edit_url'] = get_edit_post_link( $post->ID, 'raw' );
	}

	return $data;
}

/**
 * Форматирует данные магазина (Store Addon).
 *
 * @since 1.2.0
 *
 * @param WP_Post $store Объект записи магазина.
 * @param bool    $full  Полный режим.
 * @return array
 */
function mcpap_format_rtcl_store( WP_Post $store, bool $full = false ): array {
	$data = [
		'store_id'  => $store->ID,
		'title'     => $store->post_title,
		'slug'      => $store->post_name,
		'url'       => get_permalink( $store->ID ),
		'date'      => $store->post_date,
		'owner_id'  => (int) $store->post_author,
	];

	// Логотип.
	$logo_id = get_post_thumbnail_id( $store->ID );
	if ( $logo_id ) {
		$data['logo'] = [
			'id'  => $logo_id,
			'url' => wp_get_attachment_url( $logo_id ),
		];
	}

	// Количество объявлений.
	$listings_count = new WP_Query( [
		'post_type'      => 'rtcl_listing',
		'post_status'    => 'publish',
		'author'         => $store->post_author,
		'posts_per_page' => 1,
		'fields'         => 'ids',
	] );
	$data['listings_count'] = $listings_count->found_posts;

	if ( $full ) {
		$data['description'] = $store->post_content;

		// Контактные данные магазина.
		$data['contact'] = [
			'phone'   => get_post_meta( $store->ID, 'phone', true ) ?: null,
			'email'   => get_post_meta( $store->ID, 'email', true ) ?: null,
			'website' => get_post_meta( $store->ID, 'website', true ) ?: null,
			'address' => get_post_meta( $store->ID, 'address', true ) ?: null,
		];

		// Соцсети.
		$socials = [ 'facebook', 'twitter', 'youtube', 'linkedin', 'instagram', 'pinterest' ];
		$data['social'] = [];
		foreach ( $socials as $social ) {
			$val = get_post_meta( $store->ID, $social, true );
			if ( $val ) {
				$data['social'][ $social ] = $val;
			}
		}
		if ( empty( $data['social'] ) ) {
			unset( $data['social'] );
		}

		// Баннер.
		$banner_id = get_post_meta( $store->ID, 'banner_id', true );
		if ( $banner_id ) {
			$data['banner'] = [
				'id'  => $banner_id,
				'url' => wp_get_attachment_url( $banner_id ),
			];
		}

		// Время работы.
		$oh = get_post_meta( $store->ID, 'oh_hours', true );
		if ( $oh ) {
			$data['opening_hours'] = $oh;
		}

		// Верифицирован.
		$data['verified'] = (bool) get_post_meta( $store->ID, '_rtcl_verified', true );

		// Владелец.
		$owner = get_user_by( 'ID', $store->post_author );
		if ( $owner ) {
			$data['owner'] = [
				'id'           => $owner->ID,
				'display_name' => $owner->display_name,
				'email'        => $owner->user_email,
			];
		}

		$data['edit_url'] = get_edit_post_link( $store->ID, 'raw' );
	}

	return $data;
}

// ═════════════════════════════════════════════════════════════════════════════
// CLASSIFIED LISTING: FORM BUILDER (создание групп полей)
// ═════════════════════════════════════════════════════════════════════════════

/**
 * Регистрирует abilities для создания форм в Form Builder.
 *
 * @since 1.3.0
 */
function mcpap_register_rtcl_form_builder_abilities(): void {

	// --- Создать группу полей Form Builder ---
	wp_register_ability( 'mcpap/rtcl-create-form-group', [
		'label'       => __( 'Создать группу полей формы', 'mcp-abilities-provider' ),
		'description' => __( 'Создаёт группу кастомных полей (Form Builder) для указанных категорий объявлений с набором полей (select, radio, checkbox, text, textarea, number).', 'mcp-abilities-provider' ),
		'category'    => 'classified',
		'meta'        => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		'input_schema' => [
			'type'       => 'object',
			'required'   => [ 'title', 'fields' ],
			'properties' => [
				'title' => [
					'type'        => 'string',
					'description' => 'Название группы полей',
				],
				'category_ids' => [
					'type'        => 'array',
					'items'       => [ 'type' => 'integer' ],
					'description' => 'ID категорий rtcl_category, к которым привязывается форма',
				],
				'fields' => [
					'type'        => 'array',
					'description' => 'Массив полей формы',
					'items'       => [
						'type'       => 'object',
						'required'   => [ 'label', 'type' ],
						'properties' => [
							'label'       => [ 'type' => 'string', 'description' => 'Подпись поля' ],
							'meta_key'    => [ 'type' => 'string', 'description' => 'Ключ для хранения значения в postmeta' ],
							'type'        => [
								'type' => 'string',
								'enum' => [ 'text', 'textarea', 'select', 'radio', 'checkbox', 'number', 'url', 'date', 'color' ],
								'description' => 'Тип поля',
							],
							'required'    => [ 'type' => 'boolean', 'default' => false ],
							'placeholder' => [ 'type' => 'string', 'description' => 'Подсказка' ],
							'description' => [ 'type' => 'string', 'description' => 'Описание поля' ],
							'choices'     => [
								'type'        => 'object',
								'description' => 'Варианты для select/radio/checkbox: ключ => метка',
								'additionalProperties' => [ 'type' => 'string' ],
							],
							'default'     => [ 'type' => 'string', 'description' => 'Значение по умолчанию' ],
							'min'         => [ 'type' => 'number', 'description' => 'Минимум (для number)' ],
							'max'         => [ 'type' => 'number', 'description' => 'Максимум (для number)' ],
							'order'       => [ 'type' => 'integer', 'description' => 'Порядок отображения', 'default' => 0 ],
						],
					],
				],
			],
		],
		'execute_callback'    => function ( array $input ): array|WP_Error {

			if ( ! post_type_exists( 'rtcl_cfg' ) ) {
				return new WP_Error( 'cpt_missing', 'Custom Field Groups (rtcl_cfg) не зарегистрированы. Убедитесь, что Classified Listing Pro активен.' );
			}

			// 1. Создаём группу (rtcl_cfg).
			$group_data = [
				'post_type'   => 'rtcl_cfg',
				'post_title'  => sanitize_text_field( $input['title'] ),
				'post_status' => 'publish',
				'post_author' => get_current_user_id(),
			];

			$group_id = wp_insert_post( $group_data, true );
			if ( is_wp_error( $group_id ) ) {
				return $group_id;
			}

			// 2. Привязываем категории.
			$category_ids = ! empty( $input['category_ids'] )
				? array_map( 'absint', $input['category_ids'] )
				: [];

			update_post_meta( $group_id, '_rtcl_cfg_categories', $category_ids );

			// 3. Создаём поля (rtcl_cf) как дочерние посты.
			$created_fields = [];
			$order          = 0;

			foreach ( $input['fields'] as $field_def ) {
				$order++;
				$field_title = sanitize_text_field( $field_def['label'] ?? '' );
				$meta_key    = sanitize_text_field( $field_def['meta_key'] ?? sanitize_title( $field_title ) );
				$field_type  = sanitize_text_field( $field_def['type'] ?? 'text' );

				$field_post_data = [
					'post_type'    => 'rtcl_cf',
					'post_title'   => $field_title,
					'post_status'  => 'publish',
					'post_parent'  => $group_id,
					'menu_order'   => isset( $field_def['order'] ) ? absint( $field_def['order'] ) : $order,
					'post_author'  => get_current_user_id(),
				];

				$field_id = wp_insert_post( $field_post_data, true );
				if ( is_wp_error( $field_id ) ) {
					continue;
				}

				// Мета-поля поля.
				update_post_meta( $field_id, '_meta_key', $meta_key );
				update_post_meta( $field_id, '_type', $field_type );
				update_post_meta( $field_id, '_required', ! empty( $field_def['required'] ) ? '1' : '0' );
				update_post_meta( $field_id, '_placeholder', sanitize_text_field( $field_def['placeholder'] ?? '' ) );
				update_post_meta( $field_id, '_description', sanitize_text_field( $field_def['description'] ?? '' ) );

				// Числовые пределы.
				if ( isset( $field_def['min'] ) ) {
					update_post_meta( $field_id, '_min', sanitize_text_field( $field_def['min'] ) );
				}
				if ( isset( $field_def['max'] ) ) {
					update_post_meta( $field_id, '_max', sanitize_text_field( $field_def['max'] ) );
				}

				// Варианты выбора (select, radio, checkbox).
				if ( ! empty( $field_def['choices'] ) && is_array( $field_def['choices'] ) ) {
					$sanitized_choices = [];
					foreach ( $field_def['choices'] as $choice_key => $choice_label ) {
						$sanitized_choices[ sanitize_text_field( $choice_key ) ] = sanitize_text_field( $choice_label );
					}
					$options = [
						'default' => isset( $field_def['default'] ) ? sanitize_text_field( $field_def['default'] ) : null,
						'choices' => $sanitized_choices,
					];
					update_post_meta( $field_id, '_options', $options );
				}

				$created_fields[] = [
					'field_id' => $field_id,
					'label'    => $field_title,
					'meta_key' => $meta_key,
					'type'     => $field_type,
				];
			}

			return [
				'group_id'      => $group_id,
				'title'         => sanitize_text_field( $input['title'] ),
				'category_ids'  => $category_ids,
				'fields_created' => count( $created_fields ),
				'fields'        => $created_fields,
				'created'       => true,
			];
		},
		'permission_callback' => function (): bool {
			return current_user_can( 'manage_options' );
		},
	] );

	// --- Удалить группу полей Form Builder ---
	wp_register_ability( 'mcpap/rtcl-delete-form-group', [
		'label'       => __( 'Удалить группу полей формы', 'mcp-abilities-provider' ),
		'description' => __( 'Удаляет группу кастомных полей Form Builder вместе со всеми её полями.', 'mcp-abilities-provider' ),
		'category'    => 'classified',
		'meta'        => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
		'input_schema' => [
			'type'       => 'object',
			'required'   => [ 'group_id' ],
			'properties' => [
				'group_id'     => [ 'type' => 'integer', 'description' => 'ID группы полей (rtcl_cfg)' ],
				'force_delete' => [ 'type' => 'boolean', 'default' => true ],
			],
		],
		'execute_callback'    => function ( array $input ): array|WP_Error {
			$group_id = absint( $input['group_id'] );
			$post     = get_post( $group_id );

			if ( ! $post || 'rtcl_cfg' !== $post->post_type ) {
				return new WP_Error( 'not_found', 'Группа полей не найдена' );
			}

			// Удаляем дочерние поля (rtcl_cf).
			$fields = get_posts( [
				'post_type'   => 'rtcl_cf',
				'post_parent' => $group_id,
				'numberposts' => 200,
				'fields'      => 'ids',
			] );
			foreach ( $fields as $field_id ) {
				wp_delete_post( $field_id, true );
			}

			wp_delete_post( $group_id, (bool) ( $input['force_delete'] ?? true ) );

			return [
				'group_id'       => $group_id,
				'deleted'        => true,
				'fields_deleted' => count( $fields ),
			];
		},
		'permission_callback' => function (): bool {
			return current_user_can( 'manage_options' );
		},
	] );
}

// Регистрация Form Builder abilities.
add_action( 'wp_abilities_api_init', function (): void {
	if ( defined( 'RTCL_VERSION' ) && post_type_exists( 'rtcl_cfg' ) ) {
		mcpap_register_rtcl_form_builder_abilities();
	}
}, 20 );
