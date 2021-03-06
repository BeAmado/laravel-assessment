<?php

namespace Tests\Feature;

use Tests\TestCase;
use Faker\Factory as Faker;
use App\Services\UtilsService;

class ToursApiTest extends TestCase
{
    protected function repository()
    {
        return app()->make('App\Contracts\ToursRepository');
    }

    protected function generateData()
    {
        return [
            'start' => Faker::create()->dateTime()->format('Y-m-d H:i:s'),
            'end' => Faker::create()->dateTime()->format('Y-m-d H:i:s'),
            'price' =>  \rand(100, 9000),
        ];
    }

    public function testTheGetRequestForListingTheToursReturnsAJsonWithAllTheTours()
    {
        // have at least 3 records in the database
        \array_map(function() {
            $this->repository()->create($this->generateData());
        }, \range(1, 3));

        $dataCount = \count($this->repository()->all());
        $this->assertTrue($dataCount >= 3);

        $response = $this->get('/tours');
        $response->assertStatus(200);

        $tours = \json_decode($response->content(), true);

        $this->assertSame(
            $dataCount,
            \count($tours)
        );
    }

    public function testTheRequestForListingTheToursCanBeMadeWithFilteringOptions()
    {
        \array_map(function($tour) {
            $this->repository()->destroy($tour['id']);
        }, $this->repository()->all()->toArray());

        $tour1 = [
            'start' => '2021-01-01T00:00:00',
            'end' => '2022-12-31T23:59:59',
            'price' => 9990,
        ];

        $tour2 = [
            'start' => '2021-01-01T00:00:00',
            'end' => '2021-01-05T23:59:59',
            'price' => 70,
        ];

        \array_map(function($tour) {
            $this->repository()->create($tour);
        }, [$tour1, $tour2]);

        \array_map(function() {
            $this->repository()->create($this->generateData());
        }, \range(1, 5));

        $response1 = $this->get('/tours?price[gt]=90&limit=4');
        $response2 = $this->get('/tours?start[eq]=2021-01-01T00:00:00');

        $response1->assertStatus(200);
        $response2->assertStatus(200);

        $this->assertSame(
            '4-2',
            \implode('-', [
                \count(\json_decode($response1->content(), true)),
                \count(\json_decode($response2->content(), true)),
            ])
        );
    }

    /**
     * Wrapper method
     */
    protected function equalDates($d1, $d2)
    {
        return (new UtilsService)->equalDates($d1, $d2);
    }

    public function testTheRequestToGetASpecificTourReturnsItsDataAsAJsonObject()
    {
        $data = $this->generateData();
        
        $id = $this->repository()->create($data);

        $response = $this->get('/tours/' . $id);

        $response
            ->assertStatus(200)
            ->assertJson(\array_merge(
                ['id' => $id],
                $data
            ));
    }

    public function testTheRequestToCreateANewTourReturnsTheIdOfTheNewRecord()
    {
        $data = $this->generateData();

        $response = $this->post('/tours', $data);

        $response
            ->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => null,
            ]);

        $id = \json_decode($response->content())->id;

        $tour = $this->repository()->find($id);

        $this->assertSame('1-1-1', \implode('-', [
            (int) ($tour->price == $data['price']),
            (int) $this->equalDates($data['start'], $tour->start),
            (int) $this->equalDates($data['end'], $tour->end),
        ]));
    }

    public function testTheRequestToDeleteATourReturnsAJsonWithTheAttributeSuccessAsTrueWhenSuccessful()
    {
        $id = $this->repository()->create($this->generateData());

        $totalBefore = \count($this->repository()->all());

        $response = $this->delete('/tours/' . $id);

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertSame(
            $totalBefore - 1,
            \count($this->repository()->all())
        );
    }

    public function testTheRequestToDeleteATourReturnsAJsonWithTheAttributeSuccessAsFalseWhenNotSuccessful()
    {
        $totalBefore = \count($this->repository()->all());

        $response = $this->delete('/tours/' . 99999);

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => false,
            ]);

        $this->assertSame(
            $totalBefore,
            \count($this->repository()->all())
        );
    }

    public function testTheRequestForUpdatingATourReturnsAJsonWithTheAttributeSuccessAsTrueWhenSuccessful()
    {
        $id = $this->repository()->create([
            'start' => '2020-08-02',
            'end' => '2020-08-09',
            'price' => 50.00,
        ]);

        $response = $this->json('PUT', '/tours/' . $id, [
            'price' => 29.90
        ]);
        
        $response->assertStatus(200);
    }

    public function testTheRequestForUpdatingATourCreatesANewOneWithTheSpecifiedIdThereWasNotATourWithThatId()
    {
        $id = \rand(99999, 9888899998);
        $data = $this->generateData();
        $totalBefore = \count($this->repository()->all());
        $response = $this->json('PUT', '/tours/' . $id, $data);

        $response
            ->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        $tour = $this->repository()->find($id);

        $this->assertSame('1-1-1', \implode('-', [
            (int) ($tour->price == $data['price']),
            (int) $this->equalDates($data['start'], $tour->start),
            (int) $this->equalDates($data['end'], $tour->end),
        ]));
    }
}
