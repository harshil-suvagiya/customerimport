<?php
namespace BV\CustomerImport\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Magento\Framework\File\Csv;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Customer\Model\CustomerFactory;
use Magento\Framework\Serialize\Serializer\Json;

class Importcustomer extends Command
{

    const PROFILENAME = 'profile-name';
    const SOURCE = 'source';

     /**
      * @var \Magento\Store\Model\StoreManagerInterface
      */
    protected $storeManager;

    /**
     * @var \Magento\Customer\Model\CustomerFactory
     */
    protected $customerFactory;

    /**
     * @var \Magento\Framework\File\Csv
     */
    protected $csv;

    /**
     * @var \Magento\Framework\Filesystem\Driver\File
     */
    protected $file;

    /**
     * @var \Magento\Framework\Serialize\Serializer\Json
     */
    protected $json;

    /**
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Customer\Model\CustomerFactory $customerFactory
     * @param \Magento\Framework\File\Csv $csv
     * @param \Magento\Framework\Filesystem\Driver\File $file
     * @param \Magento\Framework\Serialize\Serializer\Json $json
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        CustomerFactory $customerFactory,
        Csv $csv,
        File $file,
        Json $json
    ) {
        $this->storeManager     = $storeManager;
        $this->customerFactory  = $customerFactory;
        $this->csv = $csv;
        $this->file = $file;
        $this->json = $json;
        parent::__construct();
    }

    protected function configure()
    {
        $options=[
            new InputArgument(self::PROFILENAME, InputArgument::OPTIONAL, "Pass profile name"),
            new InputArgument(self::SOURCE, InputArgument::OPTIONAL, "Pass source path")
        ];

        $this->setName('customer:import')
            ->setDescription('Customer Import')
            ->setDefinition($options);

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $profileName = $input->getArgument(self::PROFILENAME);
        $source = $input->getArgument(self::SOURCE);
        if ($profileName && $source) {
            $customerData = '';
            if ($profileName == 'sample-csv') {
                $customerData=$this->readCsvData($source, $output);
            }

            if ($profileName == 'sample-json') {
                $customerData=$this->readJsonData($source, $output);
            }
            $this->importCustomer($customerData, $output);
        } else {
            $output->writeln("please pass profile name and source file");
        }

        return $this;
    }

    public function readJsonData($filename, $output)
    {
        try {
            if ($this->file->isExists($filename)) {
                $contents =  $this->file->fileGetContents($filename);
                $jsonDecode = $this->json->unserialize($contents);
                return $jsonDecode;
            } else {
                $output->writeln("please check file not exist");
            }
        } catch (FileSystemException $e) {
            $output->writeln($e->getMessage());
        }
    }

    public function readCsvData($csvFile, $output)
    {
        try {
            if ($this->file->isExists($csvFile)) {
                $this->csv->setDelimiter(",");
                $data = $this->csv->getData($csvFile);
                if (!empty($data)) {
                    $customers = [];
                    foreach (array_slice($data, 1) as $key => $value) {
                        $customer = [];
                        $customer['fname'] = trim($value['0']);
                        $customer['lname'] = trim($value['1']);
                        $customer['emailaddress'] = trim($value['2']);
                        $customers[] = $customer;
                    }
                    return $customers;
                }
            } else {
                $output->writeln("please check file not exist");
            }
        } catch (FileSystemException $e) {
            $output->writeln($e->getMessage());
        }
    }

    public function importCustomer($customersData, $output)
    {
        try {
            // Get Website ID
            $websiteId  = $this->storeManager->getWebsite()->getWebsiteId();
            foreach ($customersData as $customerData) {
                // Instantiate object
                $customer   = $this->customerFactory->create();
                $customer->setWebsiteId($websiteId);

                // Preparing data for new customer
                $customer->setEmail($customerData['emailaddress']);
                $customer->setFirstname($customerData['fname']);
                $customer->setLastname($customerData['lname']);
                // Save data
                $customer->save();
            }
            $output->writeln("Customer data imported successfully");
        } catch (Exception $e) {
            $output->writeln($e->getMessage());
        }
    }
}
