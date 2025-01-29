<?php


namespace App\Repository;

use App\Entity\Post;
use App\Entity\Tag;
use App\Pagination\Paginator;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;
use function Symfony\Component\String\u;


class PostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Post::class);
    }

    public function findLatest(int $page = 1, Tag $tag = null): Paginator
    {
        $qb = $this->createQueryBuilder('p')
            ->addSelect('a', 't')
            ->innerJoin('p.author', 'a')
            ->leftJoin('p.tags', 't')
            ->where('p.publishedAt <= :now')
            ->orderBy('p.publishedAt', 'DESC')
            ->setParameter('now', new \DateTime())
        ;

        if (null !== $tag) {
            $qb->andWhere(':tag MEMBER OF p.tags')
                ->setParameter('tag', $tag);
        }

        return (new Paginator($qb))->paginate($page);
    }

    /**
     * @return Post[]
     */
    public function findBySearchQuery(string $query, int $limit = Post::NUM_ITEMS): array
    {
        $searchTerms = $this->extractSearchTerms($query);

        if (0 === \count($searchTerms)) {
            return [];
        }

        $queryBuilder = $this->createQueryBuilder('p');

        foreach ($searchTerms as $key => $term) {
            $queryBuilder
                ->orWhere('p.title LIKE :t_'.$key)
                ->setParameter('t_'.$key, '%'.$term.'%')
            ;
        }

        return $queryBuilder
            ->orderBy('p.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Transforms the search string into an array of search terms.
     */
    private function extractSearchTerms(string $searchQuery): array
    {
        $searchQuery = u($searchQuery)->replaceMatches('/[[:space:]]+/', ' ')->trim();
        $terms = array_unique($searchQuery->split(' '));

        // ignore the search terms that are too short
        return array_filter($terms, function ($term) {
            return 2 <= $term->length();
        });
    }
}
