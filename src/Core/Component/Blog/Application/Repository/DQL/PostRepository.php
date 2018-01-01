<?php

declare(strict_types=1);

/*
 * This file is part of the Explicit Architecture POC,
 * which is created on top of the Symfony Demo application.
 *
 * (c) Herberto Graça <herberto.graca@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Acme\App\Core\Component\Blog\Application\Repository\DQL;

use Acme\App\Core\Component\Blog\Application\Repository\PostRepositoryInterface;
use Acme\App\Core\Component\Blog\Domain\Entity\Post;
use Acme\App\Core\Component\User\Domain\Entity\User;
use Acme\App\Core\Port\Persistence\DQL\DqlQueryBuilderInterface;
use Acme\App\Core\Port\Persistence\PersistenceServiceInterface;
use Acme\App\Core\Port\Persistence\QueryBuilderInterface;
use Acme\App\Core\Port\Persistence\QueryServiceRouterInterface;
use Acme\App\Core\Port\Persistence\ResultCollection;
use Acme\App\Core\Port\Persistence\ResultCollectionInterface;
use DateTime;

/**
 * This custom Doctrine repository contains some methods which are useful when
 * querying for blog post information.
 *
 * See https://symfony.com/doc/current/doctrine/repository.html
 *
 * @author Ryan Weaver <weaverryan@gmail.com>
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 * @author Herberto Graca <herberto.graca@gmail.com>
 */
class PostRepository implements PostRepositoryInterface
{
    /**
     * @var DqlQueryBuilderInterface
     */
    private $dqlQueryBuilder;

    /**
     * @var QueryServiceRouterInterface
     */
    private $queryService;

    /**
     * @var PersistenceServiceInterface
     */
    private $persistenceService;

    public function __construct(
        DqlQueryBuilderInterface $dqlQueryBuilder,
        QueryServiceRouterInterface $queryService,
        PersistenceServiceInterface $persistenceService
    ) {
        $this->dqlQueryBuilder = $dqlQueryBuilder;
        $this->queryService = $queryService;
        $this->persistenceService = $persistenceService;
    }

    public function find(int $id): Post
    {
        $dqlQuery = $this->dqlQueryBuilder->create(Post::class)
            ->where('Post.id = :id')
            ->setParameter('id', $id)
            ->build();

        return $this->queryService->query($dqlQuery)->getSingleResult();
    }

    public function findBySlug(string $slug): Post
    {
        $dqlQuery = $this->dqlQueryBuilder->create(Post::class)
            ->where('Post.slug = :slug')
            ->setParameter('slug', $slug)
            ->build();

        return $this->queryService->query($dqlQuery)->getSingleResult();
    }

    public function upsert(Post $entity): void
    {
        $this->persistenceService->upsert($entity);
    }

    public function delete(Post $entity): void
    {
        $this->persistenceService->delete($entity);
    }

    /**
     * @return Post[]
     */
    public function findByAuthorOrderedByPublishDate(User $user): ResultCollectionInterface
    {
        $dqlQuery = $this->dqlQueryBuilder->create(Post::class)
            ->where('Post.author = :user')
            ->orderBy('Post.publishedAt', 'DESC')
            ->setParameter('user', $user)
            ->build();

        return $this->queryService->query($dqlQuery);
    }

    /**
     * @return Post[]
     */
    public function findLatest(): ResultCollectionInterface
    {
        $dqlQuery = $this->dqlQueryBuilder->create(Post::class)
            ->addSelect('author', 'tags')
            ->join('Post.author', 'author')
            ->leftJoin('Post.tags', 'tags')
            ->where('Post.publishedAt <= :now')
            ->orderBy('Post.publishedAt', 'DESC')
            ->setParameter('now', new DateTime())
            ->build();

        return $this->queryService->query($dqlQuery);
    }

    /**
     * @return Post[]
     */
    public function findBySearchQuery(
        string $rawQuery,
        int $limit = QueryBuilderInterface::DEFAULT_MAX_RESULTS
    ): ResultCollectionInterface {
        $query = $this->sanitizeSearchQuery($rawQuery);
        $searchTerms = $this->extractSearchTerms($query);

        if (\count($searchTerms) === 0) {
            return new ResultCollection();
        }

        $this->dqlQueryBuilder->create(Post::class);

        foreach ($searchTerms as $key => $term) {
            $this->dqlQueryBuilder
                ->orWhere('Post.title LIKE :t_' . $key)
                ->setParameter('t_' . $key, '%' . $term . '%');
        }

        $this->dqlQueryBuilder->orderBy('Post.publishedAt', 'DESC')->setMaxResults($limit);

        return $this->queryService->query($this->dqlQueryBuilder->build());
    }

    /**
     * Removes all non-alphanumeric characters except whitespaces.
     */
    private function sanitizeSearchQuery(string $query): string
    {
        return trim(preg_replace('/[[:space:]]+/', ' ', $query));
    }

    /**
     * Splits the search query into terms and removes the ones which are irrelevant.
     */
    private function extractSearchTerms(string $searchQuery): array
    {
        $terms = array_unique(explode(' ', $searchQuery));

        return array_filter(
            $terms,
            function ($term) {
                return 2 <= mb_strlen($term);
            }
        );
    }
}