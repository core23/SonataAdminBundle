<?php

declare(strict_types=1);

/*
 * This file is part of the Sonata Project package.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\AdminBundle\Admin;

use Knp\Menu\ItemInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Stateless breadcrumbs builder (each method needs an Admin object).
 *
 * @author Grégoire Paris <postmaster@greg0ire.fr>
 */
final class BreadcrumbsBuilder implements BreadcrumbsBuilderInterface
{
    /**
     * @var string[]
     */
    private $config = [];

    /**
     * @param string[] $config
     */
    public function __construct(array $config = [])
    {
        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);

        $this->config = $resolver->resolve($config);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'child_admin_route' => 'edit',
        ]);
    }

    public function getBreadcrumbs(AdminInterface $admin, $action): array
    {
        $breadcrumbs = [];
        if ($admin->isChild()) {
            return $this->getBreadcrumbs($admin->getParent(), $action);
        }

        $menu = $this->buildBreadcrumbs($admin, $action);

        do {
            $breadcrumbs[] = $menu;
        } while ($menu = $menu->getParent());

        $breadcrumbs = array_reverse($breadcrumbs);
        array_shift($breadcrumbs);

        return $breadcrumbs;
    }

    /**
     * {@inheritdoc}
     * NEXT_MAJOR : make this method private.
     */
    public function buildBreadcrumbs(
        AdminInterface $admin,
        $action,
        ItemInterface $menu = null
    ): ItemInterface {
        if (!$menu) {
            $menu = $admin->getMenuFactory()->createItem('root');

            $menu->addChild(
                'link_breadcrumb_dashboard',
                [
                    'uri' => $admin->getRouteGenerator()->generate('sonata_admin_dashboard'),
                    'extras' => ['translation_domain' => 'SonataAdminBundle'],
                ]
            );
        }

        $menu = $this->createMenuItem(
            $admin,
            $menu,
            sprintf('%s_list', $admin->getClassnameLabel()),
            $admin->getTranslationDomain(),
            [
                'uri' => $admin->hasRoute('list') && $admin->hasAccess('list') ?
                $admin->generateUrl('list') :
                null,
            ]
        );

        $childAdmin = $admin->getCurrentChildAdmin();

        $this->addBreadcrumbsDropdown($admin, $menu, 'list');

        if ($childAdmin) {
            $id = $admin->getRequest()->get($admin->getIdParameter());

            $menu = $menu->addChild(
                $admin->toString($admin->getSubject()),
                [
                    'uri' => $admin->hasRoute($this->config['child_admin_route']) && $admin->hasAccess($this->config['child_admin_route'], $admin->getSubject()) ?
                    $admin->generateUrl($this->config['child_admin_route'], ['id' => $id]) :
                    null,
                    'extras' => [
                        'translation_domain' => false,
                    ],
                ]
            );

            $menu->setExtra('safe_label', false);

            $this->addBreadcrumbsDropdown($admin, $menu, 'edit');

            return $this->buildBreadcrumbs($childAdmin, $action, $menu);
        }

        if ('list' === $action) {
            $menu->setUri(false);

            return $menu;
        }

        if ('create' !== $action && $admin->hasSubject()) {
            $menu = $menu->addChild($admin->toString($admin->getSubject()), [
                'extras' => [
                    'translation_domain' => false,
                ],
            ]);

            $this->addBreadcrumbsDropdown($admin, $menu, $action);

            return $menu;
        }

        $menu = $this->createMenuItem(
            $admin,
            $menu,
            sprintf('%s_%s', $admin->getClassnameLabel(), $action),
            $admin->getTranslationDomain()
        );

        $this->addBreadcrumbsDropdown($admin, $menu, $action);

        return $menu;
    }

    /**
     * Create a new dropdown menu.
     */
    private function addBreadcrumbsDropdown(AdminInterface $admin, ItemInterface $menu, string $action): void
    {
        if ('list' === $action) {
            if ($admin->hasRoute('list') && $admin->isGranted('LIST')) {
                $this->createMenuItem(
                    $admin,
                    $menu,
                    sprintf('%s_list', $admin->getClassnameLabel()),
                    null,
                    [
                        'uri' => $admin->generateUrl('list'),
                    ]
                )
                ->setExtra('icon', 'fa fa-list');
            }

            if ($admin->hasRoute('create') && $admin->isGranted('CREATE')) {
                if ($subClasses = $admin->getSubClasses()) {
                    foreach ($subClasses as $subClass) {
                        $this->createMenuItem(
                            $admin,
                            $menu,
                            sprintf('%s_crease_%s', $admin->getClassnameLabel(), $subClass),
                            null,
                            [
                                'uri' => $admin->generateUrl('create', [
                                    'subclass' => $subClass,
                                ]),
                            ]
                        )
                        ->setExtra('icon', 'fa fa-plus-circle');
                    }
                } else {
                    $this->createMenuItem(
                        $admin,
                        $menu,
                        sprintf('%s_create', $admin->getClassnameLabel()),
                        null,
                        [
                            'uri' => $admin->generateUrl('create'),
                        ]
                    )
                    ->setExtra('icon', 'fa fa-plus-circle');
                }
            }
        } elseif ('create' !== $action) {
            $id = $admin->getRequest()->get($admin->getIdParameter());

            // TODO: detect actions list dynamic
            $links = [
                [
                    'route' => 'edit',
                    'role' => 'EDIT',
                    'icon' => 'fa fa-edit',
                ],
            ];

            if (\count($admin->getShow()) > 0) {
                $links[] = [
                    'route' => 'show',
                    'role' => 'VIEW',
                    'icon' => 'fa fa-eye',
                ];
            }

            $links[] = [
                'route' => 'history',
                'role' => 'EDIT',
                'icon' => 'fa fa-clock-o',
            ];

            foreach ($links as $attributes) {
                if ($admin->hasRoute($attributes['route']) && $admin->isGranted($attributes['role'])) {
                    $this->createMenuItem(
                        $admin,
                        $menu,
                        sprintf('%s_'.$attributes['route'], $admin->getClassnameLabel()),
                        null,
                        [
                            'uri' => $admin->generateUrl($attributes['route'], ['id' => $id]),
                        ]
                    )
                    ->setExtra('icon', $attributes['icon']);
                }
            }

            if ($admin->hasRoute('acl') && $admin->isGranted('MASTER') && $admin->isAclEnabled()) {
                $this->createMenuItem(
                    $admin,
                    $menu,
                    sprintf('%s_acl', $admin->getClassnameLabel()),
                    null,
                    [
                        'uri' => $admin->generateUrl('acl', ['id' => $id]),
                    ]
                );
            }
        }
    }

    /**
     * Creates a new menu item from a simple name. The name is normalized and
     * translated with the specified translation domain.
     *
     * @param AdminInterface $admin             used for translation
     * @param ItemInterface  $menu              will be modified and returned
     * @param string         $name              the source of the final label
     * @param string         $translationDomain for label translation
     * @param array          $options           menu item options
     */
    private function createMenuItem(
        AdminInterface $admin,
        ItemInterface $menu,
        string $name,
        ?string $translationDomain = null,
        array $options = []
    ): ItemInterface {
        $options = array_merge([
            'extras' => [
                'translation_domain' => $translationDomain,
            ],
        ], $options);

        return $menu->addChild(
            $admin->getLabelTranslatorStrategy()->getLabel(
                $name,
                'breadcrumb',
                'link'
            ),
            $options
        );
    }
}
