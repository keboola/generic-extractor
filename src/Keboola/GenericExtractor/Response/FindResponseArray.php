<?php

namespace Keboola\GenericExtractor\Response;

use Keboola\GenericExtractor\Modules\ResponseModuleInterface;
use Keboola\Juicer\Config\JobConfig;
use Keboola\Juicer\Exception\UserException;
use Psr\Log\LoggerInterface;

class FindResponseArray implements ResponseModuleInterface
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * FindResponseArray constructor.
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Try to find the data array within $response.
     *
     * @param array|object $response
     * @param JobConfig $jobConfig
     * @return array
     * @throws UserException
     */
    public function process($response, JobConfig $jobConfig)
    {
        $config = $jobConfig->getConfig();

        // If dataField doesn't say where the data is in a response, try to find it!
        if (!empty($config['dataField'])) {
            if (is_array($config['dataField'])) {
                if (empty($config['dataField']['path'])) {
                    throw new UserException("'dataField.path' must be set!");
                }

                $path = $config['dataField']['path'];
            } elseif (is_scalar($config['dataField'])) {
                $path = $config['dataField'];
            } else {
                throw new UserException("'dataField' must be either a path string or an object with 'path' attribute.");
            }

            $data = \Keboola\Utils\getDataFromPath($path, $response, ".");
            if (empty($data)) {
                $this->logger->warning("dataField '{$path}' contains no data!");
                $data = [];
            } elseif (!is_array($data)) {
                // In case of a single object being returned
                $data = [$data];
            }
        } elseif (is_array($response)) {
            // Simplest case, the response is just the dataset
            $data = $response;
        } elseif (is_object($response)) {
            // Find arrays in the response
            $arrays = [];
            foreach ($response as $key => $value) {
                if (is_array($value)) {
                    $arrays[$key] = $value;
                } // TODO else {$this->metadata[$key] = json_encode($value);} ? return [$data,$metadata];
            }

            $arrayNames = array_keys($arrays);
            if (count($arrays) == 1) {
                $data = $arrays[$arrayNames[0]];
            } elseif (count($arrays) == 0) {
                $this->logger->warning("No data array found in response! (endpoint: {$config['endpoint']})", [
                    'response' => json_encode($response)
                ]);
                $data = [];
            } else {
                $e = new UserException(
                    "More than one array found in response! Use 'dataField' parameter to specify a key to the data array. (endpoint: {$config['endpoint']}, arrays in response root: " . join(", ", $arrayNames) . ")"
                );
                $e->setData([
                    'response' => json_encode($response),
                    'arrays found' => $arrayNames
                ]);
                throw $e;
            }
        } else {
            $e = new UserException('Unknown response from API.');
            $e->setData([
                'response' => json_encode($response)
            ]);
            throw $e;
        }

        return $data;
    }
}
