<?php
/**
 * Created by PhpStorm.
 * User: Lior Nitzan
 * Date: 23/05/14
 * Time: 14:23
 */

require_once('invoicer/html2pdf.class.php');

class Invoice extends PDO {
    //properties
    public $_dbh; // connection to DB via PDO
    public $_ID;
    public $_fileName;
    public $_timeZone;
    public $_issueDate;
    public $_companyName;
    public $_clientID;
    public $_clientCompanyName;
    public $_clientCompanyAddress;
    public $_clientFullName;
    public $_clientEmail;
    public $_totalCredits;
    public $_totalEuro;
    public $_paymentMethod;
    public $_cardLastFourDigits;
    public $_couponName;
    public $_couponCredits;
    public $_HTMLContent;
    public $_HTML2PDF;


    //Constructor connects to DB
    public function Invoice($clientID,$totalCredits,$totalEuro,$paymentMethod,$cardLastFourDigits,$couponName, $couponCredits) {
        //Connect to db
        $this->connectToDB();

        //Get user's data
        $userArr = $this->getUserByID($clientID); //get the client array from DB by it's ID

        $this->_timeZone = date_default_timezone_set('Asia/Jerusalem'); // insert your timezone here
        $this->_issueDate = date('M d, Y');                             // choose any date format
        $this->_companyName = "Simple Solutions LTD";                   // your company name
        $this->_companyAddress = "124 fake Street";                     // your company address
        $this->_companyCity = "Tel Aviv";                               // your company city
        $this->_companyCountry = "Israel";                              // your company country
        $this->_clientID = $userArr['id'];                              // client ID from array
        $this->_clientCompanyName = $userArr['company_name'];           // client company name
        $this->_clientCompanyAddress = $userArr['company_address'];     // client company address
        $this->_clientCompanyNumber = $userArr['company_number'];       // client company registered numbers
        $this->_clientFullName = $userArr['first_name']." ".$userArr['last_name'];  // client first & last name
        $this->_clientEmail = $userArr['email'];                        // client email
        $this->_totalCredits = $totalCredits;                           // client credits
        $this->_totalEuro = $totalEuro;                                 // client money in euro
        $this->_paymentMethod = $paymentMethod;                         // client payment method
        $this->_cardLastFourDigits = $cardLastFourDigits;               // client last 4 digits from credit card
        $this->_couponName = $couponName;                               // client coupon name if has one
        $this->_couponCredits = $couponCredits;                         // client coupon credits

        //Generate new invoice ID and insert to DB
        $this->genID();

        //Create the invoice html
        $this->_HTMLContent = $this->genHTML();

        //Create html2pdf object
        $this->_HTML2PDF = new HTML2PDF('P', 'A4', 'en');

        //Convert the html into pdf
        $this->_HTML2PDF->pdf->SetAuthor($this->_companyName);
        $this->_HTML2PDF->pdf->SetTitle("invoice".$this->_ID);
        $this->_HTML2PDF->pdf->SetSubject($this->_companyName." Invoice #".$this->_ID." issued for ".$this->_clientCompanyName);
        $this->_HTML2PDF->pdf->SetKeywords($this->_companyName, "keywords to help SEO","keywords to help SEO" ); // no limits here..
        $this->_HTML2PDF->writeHTML($this->getHTMLContent());

        //Open client dir if not exist yet & save pdf
        $folderPath = "your-path/invoices/";
        if (!file_exists($folderPath)) mkdir($folderPath, 0755, true);
        $randToken = bin2hex(openssl_random_pseudo_bytes(8));
        $pathAndFile = $folderPath."i".$this->_ID."_".$randToken.".pdf";
        $this->_fileName = "i".$this->_ID."_".$randToken.".pdf";
        $this->_HTML2PDF->Output($pathAndFile,"F"); // create the file on the server

        //Or you can just get an output with no saving data.
        //$this->_HTML2PDF->Output("invoice_".$this->_ID.".pdf");

        //Save file name in 'invoices' table
        $fileName = $this->_fileName;
        $id = $this->_ID;
        $stmt = $this->_dbh->prepare("UPDATE `invoices` SET `file_name` = :file_name WHERE `id` = :id");
        $stmt->bindParam(':file_name', $fileName, PDO::PARAM_STR);
        $stmt->bindParam(':id', $id, PDO::PARAM_STR);
        $stmt->execute();

    }

    //DB connection
    public function connectToDB(){

        $testMODE = false; //switch to local db if true

        if ($testMODE){
            $dsn = 'mysql:host=127.0.0.1;dbname=your-local-db-name';
            $username = 'root';
            $password = '';
        }
        else{
            $dsn = 'mysql:host=localhost;dbname=your-production-db-name';
            $username = 'root';
            $password = 'your-password';
        }

        try {
            $this->_dbh = new PDO($dsn,$username,$password,array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
            /*** echo a message saying we have connected ***/
            //echo "Successfully connected to the database";
        }

        catch(PDOException $e)
        {
            echo $e->getMessage();
        }

    }


    //Getters

    //Get users info from DB
    public function getUserByID($id){
        $userStmt = $this->_dbh->query("SELECT * FROM clients WHERE id='".$id."'");
        $userArr = $userStmt->fetch();
        return $userArr;
    }

    //Generates new invoice ID
    public function genID(){

        $stmt = $this->_dbh->prepare("INSERT INTO invoices (clientID, credits, euro, issued_unix)
        VALUES (:clientID, :credits, :euro, :issued_unix)");

        $clientID = $this->_clientID;
        $credits = $this->_totalCredits;
        $euro = $this->_totalEuro;
        $unix = time();

        $stmt->bindParam(':clientID', $clientID, PDO::PARAM_STR);
        $stmt->bindParam(':credits', $credits, PDO::PARAM_STR);
        $stmt->bindParam(':euro', $euro, PDO::PARAM_STR);
        $stmt->bindParam(':issued_unix', $unix , PDO::PARAM_STR);
        $stmt->execute();

        $this->_ID = $this->_dbh->lastInsertId('id');

    }

    //Html content generator
    public function genHTML(){

        $content =
            "<table border='0' cellspacing='0' cellpadding='0'>
        <tr>
            <td>
                <img src='PHPClasses/invoicer/logo.jpg' />
            </td>
            <td>
                <img src='PHPClasses/invoicer/space1.jpg' />
            </td>
            <td>
                <img src='PHPClasses/invoicer/invoice-title.jpg' />
            </td>
        </tr>
        <tr>
            <td style='font-size:18px'>
            ".$this->_companyName."<br />
            ".$this->_companyAddress."<br />
            ".$this->_companyCity."<br />
            ".$this->_companyCountry."<br />
            <br /><br />
            <b>Bill To:</b> <br />
            ".$this->_clientCompanyName."<br />
            ".$this->_clientCompanyNumber."<br />
            ".$this->_clientCompanyAddress."<br />
            ".$this->_clientFullName."<br />
            </td>

            <td>
                <img src='PHPClasses/invoicer/space1.jpg' />
            </td>

            <td align='right'>

                <br><b style='font-size:20px'>Invoice #
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                </b><br /><br />

                <span style='font-size:18px'>".$this->_ID."
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                </span><br />

                <span><img src='PHPClasses/invoicer/invoice-seperator.jpg' /></span><br />
                <b style='font-size:20px'>Date
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                </b><br /><br />
                <span style='font-size:18px'>".$this->_issueDate."
                &nbsp;&nbsp;&nbsp;&nbsp;
                </span><br />

                <span><img src='PHPClasses/invoicer/invoice-seperator.jpg' /></span><br />
                <b style='font-size:20px'>Total Amount
                &nbsp;&nbsp;&nbsp;&nbsp;
                </b><br /><br />
                <span style='font-size:18px'>€".number_format($this->_totalEuro)."
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                </span>
            </td>
        </tr>
        <tr>
            <td colspan='3'>
                <img src='PHPClasses/invoicer/space2.jpg' />
            </td>
        </tr>
        <tr style='background-color:#10aed3; font-size:21px;  align='center'>
            <td style='height:120px;text-align:center;color:#ffffff;border-top-left-radius:5px;border-bottom-left-radius:5px'>
                Package: <br />
                <span style='font-size:30px;font-weight:bold'>".number_format($this->_totalCredits)." Credits </span>
            </td>
            <td style='text-align:center;color:#ffffff;border-radius:-5px'>
                Price: <br />
                <span style='font-size:30px;font-weight:bold'>€".number_format($this->_totalEuro)."</span>
            </td>
            <td style='text-align:left;color:#ffffff;background-image:url(PHPClasses/invoicer/package-discount.jpg);
            background-repeat: no-repeat; background-position: 0px 8px;border-top-right-radius:5px;border-bottom-right-radius:5px'>
                &nbsp;&nbsp;
                <span style='font-size:26px;font-weight:bold;text-align:center'>€".number_format($this->getMoneySaved())."</span>
                <br />&nbsp;&nbsp;
                Saved
            </td>
        </tr>

        <tr>
            <br />
            <td colspan='3' style='text-align:center'>".$this->ifCoupon()."</td>
        </tr>

        <tr>
            <td colspan='3'>
                <img src='PHPClasses/invoicer/promo.jpg' />
            </td>
        </tr>
        <tr>
            <td colspan='3'>
                <img src='PHPClasses/invoicer/space2.jpg' />
            </td>
        </tr>

        <tr style='background-color:#9d9b98; color:#e9e9e7; font-size:16px; text-align:center;'>
            <td colspan='2' style='height:70px'>
                <strong>Support:</strong> (+44)2081 444 215
            </td>
            <td>
                <a href='https://www.facebook.com/agent.graphics' target='_blank'>
                    <img src='PHPClasses/invoicer/fb.jpg' width='29' height='29' border='0' />
                </a>
                &nbsp;&nbsp;&nbsp;
                <a href='https://www.twitter.com/agent.graphics' target='_blank'>
                    <img src='PHPClasses/invoicer/twitter.jpg' width='28' height='29' border='0' />
                </a>
                &nbsp;&nbsp;&nbsp;
                <a href='https://www.linkedin.com/agent.graphics' target='_blank'>
                    <img src='PHPClasses/invoicer/linkedin.jpg' width='29' height='29' border='0' />
                </a>
            </td>
        </tr>
        <tr>
            <td colspan='3' style='text-align:center'>
                <br />
                Simple Solutions Ltd © 2015 | visit our <a href='http://simple-solutions.xxx/legal.php' target='_blank'>Privacy Policy</a> & <a href='http://simple-solutions.xxx/legal.php' target='_blank'>Terms Of Use</a>
            </td>
        </tr>
</table>";
        return $content;
    }

    public function ifCoupon(){
        $coupon = (int) $this->_couponCredits;
        if ($coupon > 0){
            return "You got extra ".$coupon." credits for using the ".$this->_couponName." coupon.";
        }
    }

    public function getMoneySaved(){
        $credits = (int) $this->_totalCredits;
        $euro = (float) $this->_totalEuro;
        if ($credits < 1500){
            return 0;
        }
        elseif ($credits >= 1500 && $credits < 3000){
            return $euro * 0.05;
        }
        elseif ($credits >= 3000){
            return $euro * 0.10;
        }
    }

    public function getDBH(){
        return $this->_dbh;
    }

    public function getID(){
        return $this->_ID;
    }

    public function getTimeZone(){
        return $this->_timeZone;
    }

    public function getIssueDate(){
        return $this->_issueDate;
    }

    public function getCompanyName(){
        return $this->_companyName;
    }

    public function getClientID(){
        return $this->_clientID;
    }

    public function getClientCompanyName(){
        return $this->_clientCompanyName;
    }

    public function getClientCompanyAddress(){
        return $this->_clientCompanyAddress;
    }

    public function getClientFullName(){
        return $this->_clientFullName;
    }

    public function getClientEmail(){
        return $this->_clientEmail;
    }

    public function getTotalCredits(){
        return $this->_totalCredits;
    }

    public function getTotalEuro(){
        return $this->_totalEuro;
    }

    public function getPaymentMethod(){
        return $this->_paymentMethod;
    }

    public function getCardLastFourDigits(){
        return $this->_cardLastFourDigits;
    }

    public function getHTMLContent(){
        return $this->_HTMLContent;
    }
    public function getFileName(){
        return $this->_fileName;
    }
}

//Test it like this
//$i = new Invoice(1,100,900,"Visa",3636);
