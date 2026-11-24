<?php

namespace App\DataFixtures;

use App\Entity\Artist;
use App\Entity\Artwork;
use App\Entity\ArtworkImage;
use App\Entity\Category;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class CatalogFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        // 1. Create Categories
        $categoriesData = [
            ['name' => 'Expressionnisme Abstrait', 'slug' => 'expressionnisme-abstrait', 'description' => 'Mouvement artistique caractérisé par des formes libres, des textures dynamiques et une forte intensité émotionnelle.'],
            ['name' => 'Art Géométrique', 'slug' => 'art-geometrique', 'description' => 'Créations basées sur des compositions de figures géométriques pures, de lignes et d\'aplats de couleurs harmonieux.'],
            ['name' => 'Minimalisme Abstrait', 'slug' => 'minimalisme-abstrait', 'description' => 'Épure formelle mettant en valeur l\'espace, la lumière et la simplicité absolue des matières.'],
            ['name' => 'Art Numérique Abstrait', 'slug' => 'art-numerique-abstrait', 'description' => 'Exploration contemporaine générée par des algorithmes et techniques visuelles modernes.'],
        ];

        $categories = [];
        foreach ($categoriesData as $cData) {
            $cat = new Category();
            $cat->setName($cData['name'])
                ->setSlug($cData['slug'])
                ->setDescription($cData['description']);
            $manager->persist($cat);
            $categories[$cData['slug']] = $cat;
        }

        // 2. Create Artists
        $artistsData = [
            [
                'name' => 'Elena Rostova',
                'biography' => 'Peintre contemporaine d\'origine slave, Elena explore les synergies de couleurs primaires et les vibrations chromatiques à travers de grands formats texturés à l\'huile et au couteau.',
                'country' => 'France / Russie',
                'website' => 'https://elena-rostova.art',
            ],
            [
                'name' => 'Marc Delacroix',
                'biography' => 'Formé aux Beaux-Arts de Lyon, Marc est un adepte du constructivisme abstrait et des compositions géométriques en acrylique et feuille d\'or.',
                'country' => 'France',
                'website' => 'https://delacroix-atelier.fr',
            ],
            [
                'name' => 'Sofia Benali',
                'biography' => 'Artiste d\'avant-garde basée à Casablanca et Paris, Sofia fusionne la calligraphie gestuelle abstraite avec des pigments naturels et sables méditerranéens.',
                'country' => 'Maroc',
                'website' => 'https://sofiabenali-studio.com',
            ],
            [
                'name' => 'Alexander Vane',
                'biography' => 'Pionnier du minimalisme néo-abstrait, Alexander travaille sur des toiles monochromes rythmées par de légères incisions et reflets de lumière.',
                'country' => 'Canada',
                'website' => 'https://alexandervane.ca',
            ],
        ];

        $artists = [];
        foreach ($artistsData as $aData) {
            $artist = new Artist();
            $artist->setName($aData['name'])
                ->setBiography($aData['biography'])
                ->setCountry($aData['country'])
                ->setWebsite($aData['website']);
            $manager->persist($artist);
            $artists[] = $artist;
        }

        // 3. Create Artworks
        $artworksData = [
            [
                'title' => 'Symphonie en Bleu Majeur',
                'category' => 'expressionnisme-abstrait',
                'artist' => $artists[0],
                'price' => '3200.00',
                'status' => 'AVAILABLE',
                'technique' => 'Huile et couteau sur toile',
                'dimensions' => '120 x 90 cm',
                'year' => 2024,
                'description' => 'Une plongée fascinante dans les profondeurs de l\'outremer et du cobalt. Les touches de couteau créent un relief saisissant reflétant la lumière selon l\'angle de vue.',
                'image' => 'https://images.unsplash.com/photo-1541701494587-cb58502866ab?w=800&auto=format&fit=crop&q=80',
            ],
            [
                'title' => 'Équilibre et Fractures',
                'category' => 'art-geometrique',
                'artist' => $artists[1],
                'price' => '2450.00',
                'status' => 'AVAILABLE',
                'technique' => 'Acrylique et feuille d\'or',
                'dimensions' => '100 x 100 cm',
                'year' => 2023,
                'description' => 'Un jeu rigoureux de diagonales et de sections dorées qui structurent le vide et insufflent une dynamique sculpturale à la toile.',
                'image' => 'https://images.unsplash.com/photo-1579783900882-c0d3dad7b119?w=800&auto=format&fit=crop&q=80',
            ],
            [
                'title' => 'Souffle d\'Orient N°4',
                'category' => 'expressionnisme-abstrait',
                'artist' => $artists[2],
                'price' => '4800.00',
                'status' => 'AUCTION',
                'technique' => 'Pigments naturels et sable sur lin brut',
                'dimensions' => '150 x 110 cm',
                'year' => 2025,
                'description' => 'Gestuelle puissante évoquant les vents du désert et la calligraphie intuitive. Œuvre majeure sélectionnée pour la vente aux enchères d\'automne.',
                'image' => 'https://images.unsplash.com/photo-1579783902614-a3fb3927b675?w=800&auto=format&fit=crop&q=80',
            ],
            [
                'title' => 'Silence Blanc',
                'category' => 'minimalisme-abstrait',
                'artist' => $artists[3],
                'price' => '1800.00',
                'status' => 'AVAILABLE',
                'technique' => 'Technique mixte sur panneau bois',
                'dimensions' => '80 x 80 cm',
                'year' => 2024,
                'description' => 'Une composition minimaliste épurée où la texture blanche capte les nuances d\'ombres pour une atmosphère zen et intemporelle.',
                'image' => 'https://images.unsplash.com/photo-1550684848-fac1c5b4e853?w=800&auto=format&fit=crop&q=80',
            ],
            [
                'title' => 'Aurore Boréale Digitale',
                'category' => 'art-numerique-abstrait',
                'artist' => $artists[0],
                'price' => '1200.00',
                'status' => 'AVAILABLE',
                'technique' => 'Impression d\'art sur aluminium dibond (Tirage 1/5)',
                'dimensions' => '90 x 60 cm',
                'year' => 2025,
                'description' => 'Dégradés néon et formes spectrales inspirées des phénomènes magnétiques polaires.',
                'image' => 'https://images.unsplash.com/photo-1518640467707-6811f4a6ab73?w=800&auto=format&fit=crop&q=80',
            ],
            [
                'title' => 'Constellation Géométrique II',
                'category' => 'art-geometrique',
                'artist' => $artists[1],
                'price' => '3100.00',
                'status' => 'AUCTION',
                'technique' => 'Huile et pigments sur toile',
                'dimensions' => '130 x 95 cm',
                'year' => 2023,
                'description' => 'Étude sur le rythme des formes polygonales et l\'illusion d\'optique en perspective inversée.',
                'image' => 'https://images.unsplash.com/photo-1549490349-8643362247b5?w=800&auto=format&fit=crop&q=80',
            ],
            [
                'title' => 'Mémoires Telluriques',
                'category' => 'expressionnisme-abstrait',
                'artist' => $artists[2],
                'price' => '5400.00',
                'status' => 'SOLD',
                'technique' => 'Acrylique, cendre et terre d\'ocre',
                'dimensions' => '160 x 120 cm',
                'year' => 2022,
                'description' => 'Œuvre puissante acquise par une collection privée lors de la biennale de Marrakech.',
                'image' => 'https://images.unsplash.com/photo-1547891654-e66ed7ebb968?w=800&auto=format&fit=crop&q=80',
            ],
            [
                'title' => 'Lignes Infinies',
                'category' => 'minimalisme-abstrait',
                'artist' => $artists[3],
                'price' => '2100.00',
                'status' => 'AVAILABLE',
                'technique' => 'Encre de Chine et gesso',
                'dimensions' => '110 x 75 cm',
                'year' => 2024,
                'description' => 'Précision chirurgicale et sérénité. Des variations infimes de densités créent une pulsation visuelle apaisante.',
                'image' => 'https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?w=800&auto=format&fit=crop&q=80',
            ],
        ];

        foreach ($artworksData as $awData) {
            $artwork = new Artwork();
            $artwork->setTitle($awData['title'])
                ->setCategory($categories[$awData['category']])
                ->setArtist($awData['artist'])
                ->setPrice($awData['price'])
                ->setStatus($awData['status'])
                ->setTechnique($awData['technique'])
                ->setDimensions($awData['dimensions'])
                ->setYearCreated(new \DateTimeImmutable($awData['year'] . '-01-01'))
                ->setDescription($awData['description']);

            $manager->persist($artwork);

            $image = new ArtworkImage();
            $image->setArtwork($artwork)
                ->setFilePath($awData['image'])
                ->setAltText($awData['title'])
                ->setIsPrimary(true)
                ->setDisplayOrder(1);

            $manager->persist($image);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
        ];
    }
}
