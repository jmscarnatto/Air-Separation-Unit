<?php
require_once 'DbConnector.php';
class Simulation{
	public $PlantName;
	public $energy;
	private $prodList = array( "LOX", "HLOX", "HHLOX", "LIN", "HLIN", "LAR", "CLAR", "HLAR", "KrXe",
	 "GOX1_S3", "GOX2_S3", "GOX3_S3", "GOX4_S3", "GAN1_S3", "GAN2_S3", "GAN3_S3", "GAN4_S3", "GAR_S3", "PAir_S3", "NeHe_S3",
	 "GOX1_S1", "GOX2_S1", "GOX3_S1", "GOX4_S1", "GAN1_S1", "GAN2_S1", "GAN3_S1", "GAN4_S1", "GAR_S1", "PAir_S1", "NeHe_S1",
	 "GOX1_S5", "GOX2_S5", "GOX3_S5", "GOX4_S5", "GAN1_S5", "GAN2_S5", "GAN3_S5", "GAN4_S5", "GAR_S5", "PAir_S5", "NeHe_S5");
	public $weatherValues = array("AirTemp","CWTemp","RH");
	public $otherValues = array("GOXforArgon", "FeedCompTypicalFlow"=>"FeedFlow","GasCaseMax"=>"LPTurbineFlow","LINInjectionMax"=>"LINInjectionMax","MonthNumber","Yearnumber","PowerTariffe","RefCoolWTemp","RefInlAirTemp","RefRelHumAirComp");
	private $Settings = array();
	public $LOX_TT, $HLOX_TT, $HHLOX_TT, $LIN_TT, $HLIN_TT, $HHLIN_TT,$LAR_TT,$CLAR_TT,$HLAR_TT;
	public $TotO2_FT, $TotAr_FT, $TotLiq_FT, $ExcessAirforAr, $ExcessAirforN2, $v1loop,$v2loop,$v3loop;
	public $ModelAir, $ModelLPTurbine, $vGasCase, $ModelFeedPower, $ModelFeedFlow, $ModelFeed;
	public $RecyclePowerCorrection,$AirPowerCorrection, $AirPowerCorrection1, $AirPowerCorrection2;
	public $ModelGOX1Power,$ModelGOX2Power,$ModelGOX3Power,$ModelGOX4Power,$ModelGAN1Power, $ModelGAN2Power;
	public $ModelGAN3Power, $ModelGAN4Power, $ModelPAirPower,$ModelRecycle1Flow,$ModelRecycle2Flow;
	public $ModelRecycle1Power,$ModelRecycle2Power,$ModelAirFlow,$ModelLARBasedOnO2, $ModelArRecovTech;
	public $MinAirFlow,$MaxAirFlow;
	public $loopcounter, $Period, $PeriodLAR,$dateStart,$dateEnd;
	public $GAPinfo = array();

	public function __set($name, $value){
		$this->$name = $value;
	}
	public function simulate(){
		$this->Inputs();
		//do{
		$this->TotLiqAndGaseousProducts();
		$this->Feed();
		$this->RecycleAndRecoveriesAndAir();

			$LARready = FALSE;

			$this->ModelLARBasedOnO2 = $this->ModelArRecovTech * $this->ModelAir * 0.00934 - $this->GAR_S1 - $this->CLAR_TT * 0.97 - $this->HLAR_TT;
			$v1 = Abs($this->ModelLARBasedOnO2 - $this->LAR_TT);
		
		$this->Products();
		$this->TotalPower();
		return $this->GAPinfo;
	}
	
	
	//***********************************************************
	// Inputs
	//***********************************************************
	function interval($start,$end){
	if(date($start)>date($end)) die ("Verify dates - start date must be before end");
	$this->MonthNumber = date('n',strtotime($end));
	$this->Yearnumber = date('Y',strtotime($end));
	$this->dateStart = date('dS',strtotime($start));
	$this->dateEnd = date('dS \of M',strtotime($end));
    $this->Period = (strtotime($end)-strtotime($start))/3600; 
	$this->PeriodLAR = $this->Period+$this->GetSettingValue("StartupTimeLAR");
	}
	function setPeriod($value){
	if(!defined("$this->Period")) define("Period",$value); 
	if(!defined("$this->PeriodLAR")) define("PediodLAR",$this->Period+$this->GetSettingValue("StartupTimeLAR"));
	}
	function productions($string){
		$product = json_decode($string,true);
		foreach ($this->prodList as $prodName){
			if (in_array($prodName,array_keys($product))){
			eval('$this->'.$prodName.'='.($product[$prodName]/1000).';');
			}else{
			eval('$this->'.$prodName.'= 0;');
			}
		}
	}
	function weather($string){
		$weatherParams = json_decode($string,true);
		foreach ($this->weatherValues as $weatherName){
			if (in_array($weatherName,array_keys($weatherParams))){
			eval('$this->'.$weatherName.'='.$weatherParams[$weatherName].';');
			}else{
			eval('$this->'.$weatherName.'= 0;');
			}
		}
	}
	function otherSettings($string){
		$otherParams = json_decode($string,true);
		foreach ($this->otherValues as $otherName){
			if (in_array($otherName,array_keys($otherParams))){
			eval('$this->'.$otherName.'='.$otherParams[$otherName].';');
			}
		}
	}
	function Inputs(){
	if(!defined("ConvFactor")) define("ConvFactor",0.00010);
	if(!defined("MaxCounter")) define("MaxCounter",100);
	if(!defined("GOXforArgon")) define("GOXforArgon", $this->GetSettingValue("GOXforArgon") * $this->Period / 1000);
    if(!defined("FeedFlow")) define("FeedFlow", $this->GetSettingValue("FeedCompTypicalFlow")) ;
    if(!defined("LPTurbineFlow")) define("LPTurbineFlow", $this->GetSettingValue("GasCaseMax")) ;
    if(!defined("LINInjectionMax")) define("LINInjectionMax", $this->GetSettingValue("LINInjectionMax")) ;
    if(!defined("MonthNumber")) define("MonthNumber", date('n')) ;
    if(!defined("Yearnumber")) define("Yearnumber", date('Y')) ;
    $this->AirTemp = $this->GetAirTemp($this->MonthNumber) ;
    $this->CWTemp = $this->GetCWTemp($this->MonthNumber);
    $this->RH = $this->GetRH($this->MonthNumber) ;
	if(!defined("RefCoolWTemp")) define("RefCoolWTemp",$this->GetSettingValue("RefCoolWTemp"));
    if(!defined("RefInlAirTemp")) define("RefInlAirTemp",$this->GetSettingValue("RefInlAirTemp"));
    if(!defined("RefRelHumAirComp")) define("RefRelHumAirComp",$this->GetSettingValue("RefRelHumAirComp"));
    $var1 = (LPTurbineFlow > 0)?1 :0;
	if(!defined("LPTurbineAutomatic")) define("LPTurbineAutomatic", $var1);
    if(!defined("StopToStartLPTurbine")) define("StopToStartLPTurbine",$this->GetSettingValue("RecycleStopLimit"));
    if(!defined("GasCaseFlow")) define("GasCaseFlow", LPTurbineFlow);
	$var2 = (LINInjectionMax > 0) ? 1: 0;
    if(!defined("LINInjectionExists")) define("LINInjectionExists",$var2);
	if(!defined("MaxLiqFactor1")) define("MaxLiqFactor1",1000);
	if(!defined("MaxLiqFactor2")) define("MaxLiqFactor2",0);
    /**    
    PowerTariffe = Cells(Range("AllInputPowerTariffe").Row, vCol1 + plantloop)
    If IsEmpty(PowerTariffe) Then
        PowerTariffe = GetTargetFromHistory(PlantName, YearMonth, "PowerTariffe")
        Cells(Range("AllInputPowerTariffe").Row, vCol1 + plantloop) = PowerTariffe
    End If
*/
    if(!defined("ActualProcessAir")) define("ActualProcessAir",0); //Cells(Range("AllInputActualCBFlow").Row, vCol1 + plantloop) * $this->Period / 1000
    if(!defined("ActualTotPC")) define("ActualTotPC", 0); //Cells(Range("AllInputActualTotPC").Row, vCol1 + plantloop) * $this->Period / 1000
    if(!defined("ActualAirPC")) define("ActualAirPC", 0); //Cells(Range("AllInputActualAirPC").Row, vCol1 + plantloop) * $this->Period / 1000
    if(!defined("ActualRec1PC")) define("ActualRec1PC",0);// Cells(Range("AllInputActualRec1PC").Row, vCol1 + plantloop) * $this->Period / 1000
    if(!defined("ActualRec2PC")) define("ActualRec2PC",0);// Cells(Range("AllInputActualRec2PC").Row, vCol1 + plantloop) * $this->Period / 1000
    if(!defined("ActualFeedPC")) define("ActualFeedPC",0);// Cells(Range("AllInputActualFeedPC").Row, vCol1 + plantloop) * $this->Period / 1000
    if(!defined("ActualGOX1PC")) define("ActualGOX1PC",0);// Cells(Range("AllInputActualGOX1PC").Row, vCol1 + plantloop) * $this->Period / 1000
    if(!defined("ActualGOX2PC")) define("ActualGOX2PC",0);// Cells(Range("AllInputActualGOX2PC").Row, vCol1 + plantloop) * $this->Period / 1000
    if(!defined("ActualGOX3PC")) define("ActualGOX3PC",0);// Cells(Range("AllInputActualGOX3PC").Row, vCol1 + plantloop) * $this->Period / 1000
    if(!defined("ActualGOX4PC")) define("ActualGOX4PC",0);// Cells(Range("AllInputActualGOX4PC").Row, vCol1 + plantloop) * $this->Period / 1000
    if(!defined("ActualGAN1PC")) define("ActualGAN1PC",0);// Cells(Range("AllInputActualGAN1PC").Row, vCol1 + plantloop) * $this->Period / 1000
    if(!defined("ActualGAN2PC")) define("ActualGAN2PC",0);// Cells(Range("AllInputActualGAN2PC").Row, vCol1 + plantloop) * $this->Period / 1000
    if(!defined("ActualGAN3PC")) define("ActualGAN3PC",0);// Cells(Range("AllInputActualGAN3PC").Row, vCol1 + plantloop) * $this->Period / 1000
    if(!defined("ActualGAN4PC")) define("ActualGAN4PC",0);// Cells(Range("AllInputActualGAN4PC").Row, vCol1 + plantloop) * $this->Period / 1000
    if(!defined("ActualPAirPC")) define("ActualPAirPC",0);// Cells(Range("AllInputActualPAirPC").Row, vCol1 + plantloop) * $this->Period / 1000
	
	}
	
	//***********************************************************
	
	/** SIMULATOR */
	function TotLiqAndGaseousProducts(){
	$this->TotLiq_FT =($this->LOX + $this->HLOX + $this->HHLOX + 0.93 * ($this->LIN + $this->HLIN) + 0.84 * ($this->LAR + $this->CLAR + $this->HLAR)) * 1000 / $this->Period;
	$this->LOX_TT = $this->Calc_ToTank(($this->LOX) / $this->Period * 1000, "LOX") * $this->Period / 1000;
	$this->HLOX_TT = $this->Calc_ToTank(($this->HLOX) / $this->Period * 1000, "HLOX") * $this->Period / 1000;
	$this->HHLOX_TT = $this->Calc_ToTank(($this->HHLOX) / $this->Period * 1000, "HHLOX") * $this->Period / 1000;
	$this->LIN_TT = $this->Calc_ToTank($this->LIN / $this->Period * 1000, "LIN") * $this->Period / 1000;
	$this->HLIN_TT = $this->Calc_ToTank(($this->HLIN) / $this->Period * 1000, "HLIN") * $this->Period / 1000;
	/**If Range("LARMode") <> 2 Then */
		$this->LAR_TT = $this->Calc_ToTank(($this->LAR) / $this->PeriodLAR * 1000, "LAR") * $this->PeriodLAR / 1000;
		$this->CLAR_TT = $this->Calc_ToTank(($this->CLAR) / $this->PeriodLAR * 1000, "CLAR") * $this->PeriodLAR / 1000;
		$this->HLAR_TT = $this->Calc_ToTank(($this->HLAR) / $this->PeriodLAR * 1000, "HLAR") * $this->PeriodLAR / 1000;
	/** Else
		LAR = Calc_FromTank(LAR_TT / $this->PeriodLAR * 1000, "LAR") * $this->PeriodLAR / 1000;
	End If */
	$this->TotLiq_TT = ($this->LOX_TT + $this->HLOX_TT + $this->HHLOX_TT + $this->KrXe + 0.93 * ($this->LIN_TT + $this->HLIN_TT) + 0.84 * ($this->LAR_TT + $this->HLAR_TT + $this->CLAR_TT)) * 1000 / $this->Period;
    $this->TotO2_FT = $this->LOX + $this->HLOX + $this->HHLOX + $this->GOX1_S3 + $this->GOX2_S3 + $this->GOX3_S3 + $this->GOX4_S3;
    $this->TotAr_FT = $this->LAR + 0.97 * $this->CLAR + $this->HLAR + $this->GAR_S3;
    $this->TotO2_TT = $this->LOX_TT + $this->HLOX_TT + $this->HHLOX_TT + $this->GOX1_S3 + $this->GOX2_S3 + $this->GOX3_S3 + $this->GOX4_S3;
    $this->TotAr_TT = $this->LAR_TT + 0.97 * $this->CLAR_TT + $this->HLAR_TT + $this->GAR_S3;
	}
	function Feed(){
    $this->ModelFeed = FeedFlow * $this->Period / 1000 * $this->GetSettingValue("FeedCompressor") ;
    $this->ModelFeedFlow = $this->ModelFeed / $this->Period;
    $this->ModelFeedPower = $this->Calc_ModelFeedPower($this->ModelFeedFlow);
	}
	function RecycleAndRecoveriesAndAir(){
	$this->MinAirFlow = $this->Calc_MinAirFlow();
	$this->MaxAirFlow = $this->Calc_MaxAirFlow(); 
	$v1 = ($this->GetSettingValue("PairEC")==1)? ($this->Period - $AdditionalStops) / $this->Period:1; 
	$Recycle1ICFlow = $this->Calc_RecycleFlowForIC(1, $v1 * $this->PAir_S3);
	$Recycle2ICFlow = $this->Calc_RecycleFlowForIC(2, $v1 * $this->PAir_S3);
	//Init
	$LiquidFlowRecycle1 = $this->TotLiq_TT;
	$LiquidFlowRecycle2 = 0;
	$this->ModelAir = $this->MaxAirFlow * $this->Period / 1000;
	$this->v1loop = $LiquidFlowRecycle1;
	$loopcounter = 0;
	$vGasCase = $this->GetSettingValue("GasCaseExisting");
	Do {// Iteration loop when having IC and limitation in liquid production and Argon vs ModelAir
		$MaxRecycle1Flow = $this->Calc_MaxRecycleFlow(1, 1);
		$MaxRecycle2Flow = $this->Calc_MaxRecycleFlow(2, 1);
	 
		$MinRecycle1Flow = $this->Calc_MinRecycleFlow(1, 1);
		$MinRecycle2Flow = $this->Calc_MinRecycleFlow(2, 1);
		
		$Liquefactionfactor1 = $this->Calc_Liquefactionfactor(1, $LiquidFlowRecycle1, True);
		$Liquefactionfactor2 = $this->Calc_Liquefactionfactor(2, $LiquidFlowRecycle2, True);

		$MaxLiquidFlowRecycle1 = $this->Calc_MaxLiquidFlowRecycle(1, $this->TotLiq_TT, $MaxRecycle1Flow, $Recycle1ICFlow, $Liquefactionfactor1);
		$MaxLiquidFlowRecycle2 = $this->Calc_MaxLiquidFlowRecycle(2, $this->TotLiq_TT, $MaxRecycle2Flow, $Recycle2ICFlow, $Liquefactionfactor2);

		$LiquidFlowRecycle1 = $this->Calc_LiquidFlowRecycle(1, $this->TotLiq_TT, $MaxLiquidFlowRecycle1, $LiquidFlowRecycle2);
		$LiquidFlowRecycle2 = $this->Calc_LiquidFlowRecycle(2, $this->TotLiq_TT, $MaxLiquidFlowRecycle2, $LiquidFlowRecycle1);
		   
		$ModelRecycle1 = $this->Calc_ModelRecycle(1, $LiquidFlowRecycle1, $Recycle1ICFlow, $Liquefactionfactor1);
		$ModelRecycle2 = $this->Calc_ModelRecycle(2, $LiquidFlowRecycle2, $Recycle2ICFlow, $Liquefactionfactor2);
		
		$ModelGasCaseTime = $this->Calc_ModelGasCaseTime(StopToStartLPTurbine, $ModelRecycle1, $ModelRecycle2, $this->Period, $MinRecycle1Flow, $Liquefactionfactor1, $MinRecycle2Flow, $Liquefactionfactor2, GasCaseFlow, LINInjectionMax, LPTurbineAutomatic);
		/**
		If vGasCase <> 2 Then
		*/
			$ModelLPTurbine = $this->Calc_ModelLPTurbine($ModelGasCaseTime, GasCaseFlow) * 1000 / $this->Period;
			$ModelLINInjection = $this->Calc_ModelLPTurbine($ModelGasCaseTime, LINInjectionMax) * 1000 / $this->Period;
			$ModelLPTurbinekW = 0;
	/**
	Else
			ModelLPTurbine = Calc_ModelLPTurbineFlow(ModelAir, $this->Period)
			ModelLPTurbinekW = Calc_ModelLPTurbinekW(ModelLPTurbine)
			TotliqTT_kW = Calc_TotLiqTT_kW($this->TotLiq_TT, LIN, $this->Period)
			ThermalLosseskW = GetSettingValue(PlantName, "TotThermLosseskW")
			LINInjectionkW = TotliqTT_kW + ThermalLosseskW - ModelLPTurbinekW
			ModelLINInjection = Calc_ModelLINInjection2(TotliqTT_kW, ModelLPTurbinekW, $this->CWTemp, LIN, $this->Period, ThermalLosseskW, LINInjectionkW)
		End If
		*/
		$ModelRecycle1Restricted = $this->Calc_ModelRecycleRestricted(1, LPTurbineAutomatic, $ModelRecycle1, $ModelLPTurbine, $MaxRecycle1Flow, $MinRecycle1Flow, $Liquefactionfactor1, $ModelLINInjection);
		$ModelRecycle2Restricted = $this->Calc_ModelRecycleRestricted(2, LPTurbineAutomatic, $ModelRecycle2, $ModelLPTurbine, $MaxRecycle2Flow, $MinRecycle2Flow, $Liquefactionfactor2, $ModelLINInjection);
		
		$this->ModelRecycle1Flow = $this->Calc_RecycleFlow($ModelRecycle1Restricted);
		$this->ModelRecycle2Flow = $this->Calc_RecycleFlow($ModelRecycle2Restricted);
		
		$this->ModelRecycle1Power = $this->Calc_RecyclePower(1,$ModelRecycle1, $ModelGasCaseTime, $Liquefactionfactor1, $MinRecycle1Flow, $MaxRecycle1Flow, $this->ModelRecycle1Flow, $this->RecyclePowerCorrection);
		$this->ModelRecycle2Power = $this->Calc_RecyclePower(2, $ModelRecycle2, $ModelGasCaseTime, $Liquefactionfactor2, $MinRecycle2Flow, $MaxRecycle2Flow, $this->ModelRecycle2Flow, $this->RecyclePowerCorrection);
	
		//echo "<li>Rflow: ".$this->ModelRecycle1Flow."</li>";
		//echo "<li>RPow: ".$this->ModelRecycle1Power."</li>";
	
		$Recycle1FlowForIC_S1 = $this->RecycleFlowForIC_S1(1);
		$Recycle2FlowForIC_S1 = $this->RecycleFlowForIC_S1(2);
		$Recycle1FlowForIC_s3 = $this->RecycleFlowForIC_S3(1);
		$Recycle2FlowForIC_s3 = $this->RecycleFlowForIC_S3(2);

		$ModelSepBypass = $this->Calc_ModelSepBypass($this->LIN_TT + $this->HLIN_TT, $ModelLINInjection * $this->Period / 1000, $this->ModelFeed, $this->ModelAir, $this->ModelLPTurbine * $this->Period / 1000, $this->vGasCase);

		$this->ModelArRecovTech = $this->Calc_ModelArRecovTech($ModelSepBypass);
		$ModelO2RecovTech = $this->Calc_ModelO2RecovTech($ModelSepBypass);
		$ModelArRecovProd = $this->Calc_ModelArRecovProd($this->ModelArRecovTech, $this->ModelAir);
		$ModelO2RecovProd = $this->Calc_ModelO2RecovProd($this->ModelAir);
				
		$ModelAirO2 = $this->Calc_ModelAirO2($ModelO2RecovTech);
		$ModelAirN2 = $this->Calc_ModelAirN2();
		$ModelAirAr = $this->Calc_ModelAirAr($this->ModelArRecovTech);
		$ModelAirRecycle = $this->Calc_AirFlowLimiterConst($this->ModelRecycle1Flow, $this->GetSettingValue("Recycle1Maxsummer")) * $this->GetSettingValue("AirMAXsummer") * $this->Period / 1000;
		$this->ModelAir = $this->Calc_ModelAir($this->MinAirFlow, $this->MaxAirFlow, $ModelAirN2, $ModelAirAr, $ModelAirO2, $ModelAirRecycle, $ModelRecycle1Restricted, $ModelRecycle2Restricted);

				
	// ------------------ Convergence ---
		$ready = FALSE;
		$v1 = ($this->ModelAir==0)? 0 : abs($this->ModelAir - $this->v1loop) / $this->ModelAir;
		$v2 = ($LiquidFlowRecycle1==0)? 0: abs($LiquidFlowRecycle1 - $this->v2loop) / $LiquidFlowRecycle1;
		$v3 = ($LiquidFlowRecycle2==0)? 0: abs($LiquidFlowRecycle2 - $this->v3loop) / $LiquidFlowRecycle2;
		if($v1<ConvFactor&&$v2<ConvFactor&&$v3<ConvFactor&&$this->loopcounter>1) $ready = TRUE;
		$this->v1loop = $this->ModelAir;
		$this->v2loop = $LiquidFlowRecycle1;
		$this->v3loop = $LiquidFlowRecycle2;	
		$this->loopcounter++;
		
		if($this->loopcounter==MaxCounter) echo "";//"iteration loop did not converge within max limit";
    
		} while($this->loopcounter<MaxCounter&&!$ready);// 
	//--------------------------------------------------------------------------
    $ActualSepBypass = $this->Calc_ModelSepBypass($this->LIN_TT + $this->HLIN_TT, LINInjectionMax * $this->Period / 1000, FeedFlow * $this->Period / 1000, ActualProcessAir, LPTurbineFlow * $this->Period / 1000, $this->vGasCase);

    $ModelProcessFlow = $this->ModelAir * 1000 / $this->Period;
    //ModelAirFlow = (ModelAir + GetSettingValue(PlantName, "PairFromAirComp") * $this->PAir_S3) / $this->Period '(kNm3/h)
    
    $v1 = ($this->GetSettingValue("PairEC")==1)? ($this->Period - AdditionalStops) / $this->Period : 1;
    $v2 = $this->GetSettingValue("PairFromAirComp");
    $v3 = $this->GetSettingValue("PairRecycleBleed");
    $v4 = ($v2==1||$v3==1) ? 1 : 0;
    //If external compressor exists for Pair, and also bleed from main air compressor or recycle compressor, then it is assumed that the external compressor is in operation only during time when ASU plant is not in operation.
    
    $this->ModelAirFlow = ($this->ModelAir + $v1 * $v4 * $this->PAir_S3) / $this->Period; //(kNm3/h);
    $this->ModelAirPower = $this->Calc_ModelAirPower($this->ModelAirFlow, $this->AirPowerCorrection, $this->AirPowerCorrection1, $this->AirPowerCorrection2);
    
        $v1 = $ModelAirAr - max($ModelAirO2, ($this->MinAirFlow * $this->Period / 1000), $ModelAirRecycle);
        $ExcessAirforAr = max(0, $v1) / $this->Period * 1000;
        $v1 = $ModelAirN2 - max($ModelAirO2, ($this->MinAirFlow * $this->Period / 1000), $ModelAirRecycle);
        $ExcessAirforN2 = max(0, $v1) / $this->Period * 1000;
        $v1 = $ModelAirRecycle - max($ModelAirO2, ($this->MinAirFlow * $this->Period / 1000));
        $ExcessAirforLiquid = max(0, $v1) / $this->Period * 1000;
    
	/*
    // SELECT IF TO SHOW EXCESS AIR OR NOT
        Select Case Blad222.Range("ExcessAirMode")
        Case Is = 1   ' Air flow based on O2
                ExcessAirforN2 = 0
                ExcessAirforAr = 0
        Case Is = 2   ' Air flow based on O2, N2 and Ar
                If Blad222.Range("LARMode") <> 1 Then ExcessAirforAr = 0
        Case Is = 3   ' Air flow based on O2, N2 and Ar based on Plant Settings
                If GetSettingValue(PlantName, "ExcessAirN2") = "No" Then ExcessAirforN2 = 0
                If Blad222.Range("LARMode") <> 1 Or GetSettingValue(PlantName, "ExcessAirAr") = "No" Then ExcessAirforAr = 0
        End Select

    If pMarginalCostLoop = 0 Then ' no marginal cost loop
     //Fix Formats for air, recycle max and min
    //    RecFlowLimiterConst = Calc_RecFlowLimiterConst(ModelAir / $this->Period * 1000, $this->MaxAirFlow)
        RecFlowLimiterConst = 1
        Call GetLimits(pColumn, "Recycle1MinWinter", "Recycle1MaxWinter", "AllInputRec1CompFlow", "2", $this->Period, RecFlowLimiterConst, "", "", "Recycle1")
        Call GetLimits(pColumn, "Recycle2MinWinter", "Recycle2MaxWinter", "AllInputRec2CompFlow", "2", $this->Period, RecFlowLimiterConst, "", "", "Recycle2")
        Call GetLimits(pColumn, "AirMinWinter", "AirMaxWinter", "AllInputAirCompFlow", "Input 2", $this->Period, 1, "AllInputAirCompFlowMIN", "AllInputAirCompFlowMAX", "Air")
    
        LiqCap = $this->TotLiq_TT / GetSettingValue(PlantName, "TotLiqMAX")
        AirCap = ModelAirFlow * 1000 / $this->MaxAirFlow
        If MaxRecycle1Flow > 0 Then Rec1Cap = ModelRecycle1Flow * 1000 / MaxRecycle1Flow Else Rec2Cap = 0
        If MaxRecycle2Flow > 0 Then Rec2Cap = ModelRecycle2Flow * 1000 / MaxRecycle2Flow Else Rec2Cap = 0
        
    ModelO2RecovTechTarget = TotO2_TT / ModelAir / 0.2095 * (1 - GetTargetFromHistory(PlantName, YearMonth, "TargetO2Dev"))
    ModelArRecovTechTarget = ModelArRecovTech * (1 - GetTargetFromHistory(PlantName, YearMonth, "TargetArDev"))
    
    End If
	*/
	}
	function Products(){
    $this->ModelGOX1Power = $this->Calc_ModelProdCompressorPower("GOX1Comp", $this->GOX1_S1);
    $this->ModelGOX2Power = $this->Calc_ModelProdCompressorPower("GOX2Comp", $this->GOX2_S1);
    $this->ModelGOX3Power = $this->Calc_ModelProdCompressorPower("GOX3Comp", $this->GOX3_S1);
    $this->ModelGOX4Power = $this->Calc_ModelProdCompressorPower("GOX4Comp", $this->GOX4_S1);
    $this->ModelGAN1Power = $this->Calc_ModelProdCompressorPower("GAN1Comp", $this->GAN1_S1);
    $this->ModelGAN2Power = $this->Calc_ModelProdCompressorPower("GAN2Comp", $this->GAN2_S1);
    $this->ModelGAN3Power = $this->Calc_ModelProdCompressorPower("GAN3Comp", $this->GAN3_S1);
    $this->ModelGAN4Power = $this->Calc_ModelProdCompressorPower("GAN4Comp", $this->GAN4_S1);
    
    if($this->GetSettingValue("PairRecycleBleed")==1||$this->GetSettingValue("PairFromAirComp")==1) {
       if($this->GetSettingValue("PairEC")==1) {
         $v1 = AdditionalStops / $this->Period;   
		 //If external compressor exists for Pair, and also bleed from main air compressor or recycle compressor, then it is assumed that the //external compressor is in operation only during time when ASU plant is not in operation.
         $this->ModelPAirPower = v1 * Calc_ModelProdCompressorPower("PAirC", v1 * $this->PAir_S1);
       }else{
			$v1 = 1;
			$this->ModelPAirPower = Calc_ModelProdCompressorPower("PAirC", $this->PAir_S1);
			}
		}
	}
	function TotalPower(){
	//$OtherPower = GetSettingRangeSum("OtherPowerConsumers") + GetSettingRangeSum("OtherBuildingConsumer");
	$OtherPower = $this->GetSettingValue("OtherPowerConsumers") + $this->GetSettingValue("OtherBuildingConsumer");

	$TotPC = $this->ModelGOX1Power + $this->ModelGOX2Power + $this->ModelGOX3Power + $this->ModelGOX4Power + $this->ModelGAN1Power + $this->ModelGAN2Power + $this->ModelGAN3Power + $this->ModelGAN4Power + $this->ModelPAirPower;
	$TotPC += $this->ModelFeedPower + $this->ModelRecycle1Power + $this->ModelRecycle2Power + $this->ModelAirPower + $OtherPower;
	//$TotPCTarget = $TotPC * (1 + GetTargetFromHistory(PlantName, YearMonth, "TargetTotPCDev") / 100);

		$BackUpEnergy = 0.16 * ($this->GOX1_S5 + $this->GOX2_S5 + $this->GOX3_S5 + $this->GOX4_S5) + 0.15 * ($this->GAN1_S5 + $this->GAN2_S5 + $this->GAN3_S5 + $this->GAN4_S5) + 0.13 * $this->GAR_S5;
		$v1 = $this->GetSettingValue("SteamFactor");
		$v2 = $this->GetSettingValue("BackupVaporisationSteam"); // Steam evap /Yes or No
		if($v1==0) $v1 = 1;
		$SteamBackup_Ton = $v2 * $BackUpEnergy / $v1 * 1000;
		$v2 = $this->GetSettingValue("SteamConsumption");
		$v3 = $this->GetSettingValue("ProcessAirFlowDesign");
		$SteamMS_Ton = $v2 * $this->ModelAirFlow * $this->Period / $v3;
		$TotalSteamEnergy = ($SteamMS_Ton + $SteamBackup_Ton) * $v1 / 1000;
		$v4 = $this->GetSettingValue("BackupVaporisationWarmWater");
		$TotalWarmWaterEnergy = $v4 * $BackUpEnergy;
		
	$TotPE = ($TotalSteamEnergy + $TotalWarmWaterEnergy + $BackUpEnergy) / $this->Period * 1000 + $TotPC;
	$CommonPowerConsumers = $this->Calc_CommonPowerConsumers($this->Period, $TotPC, $this->ModelAirPower, $this->ModelRecycle1Power, $this->ModelRecycle2Power, $this->ModelFeedPower, $this->ModelGOX1Power, $this->ModelGOX2Power, $this->ModelGOX3Power, $this->ModelGOX4Power, $this->ModelGAN1Power, $this->ModelGAN2Power, $this->ModelGAN3Power, $this->ModelGAN4Power, $this->ModelPAirPower, $OtherPower);
	$ActualCommonPowerConsumers = $this->Calc_CommonPowerConsumers($this->Period, ActualTotPC / $this->Period * 1000, ActualAirPC / $this->Period * 1000, ActualRec1PC / $this->Period * 1000, ActualRec2PC / $this->Period * 1000, ActualFeedPC / $this->Period * 1000, ActualGOX1PC / $this->Period * 1000, ActualGOX2PC / $this->Period * 1000, ActualGOX3PC / $this->Period * 1000, ActualGOX4PC / $this->Period * 1000, ActualGAN1PC / $this->Period * 1000, ActualGAN2PC / $this->Period * 1000, ActualGAN3PC / $this->Period * 1000, ActualGAN4PC / $this->Period * 1000, ActualPAirPC / $this->Period * 1000, $OtherPower);
	
	/*
	echo "<div class='SimResult'><ul>";
	echo "<li>GOX2: ".$this->GOX2_S1." </li>";
	//echo "<li><p>Plant : ".$this->PlantName." </p></li>";
	//echo "<li>Period: ".$this->Period." </li>";
	echo "<li>Period: ".$this->dateStart." - ".$this->dateEnd." </li>";
	//echo "<li>Air model flow: ".$this->represent($this->ModelAirFlow)." </li>";
	//echo "<li>Air model power: ".$this->represent($this->ModelAirPower)." </li>";
	//echo "<li>Recycle model flow: ".$this->represent($this->ModelRecycle1Flow)." </li>";
	//echo "<li>Recycle model power: ".$this->represent($this->ModelRecycle1Power)." </li>";
	echo "<li>ACTUAL energy: ".$this->represent(($this->energy)/1000,0)." Mwh </li>"; 
	echo "<li>MODEL energy: ".$this->represent(($TotPC * $this->Period)/1000,0)." Mwh</li>"; 
	//echo "<li>Total model power: ".$TotPE." </li>"; 
	echo "<li><b>Energy Gap: ".$this->represent(($this->energy - ($TotPE * $this->Period))*100/($TotPE * $this->Period))."% </b></li>"; 
	//echo "<li>Argon model flow: ".$this->represent($this->ModelLARBasedOnO2)." </li>"; 
	//echo "<li><b>Argon Gap: ".$this->represent(($this->ModelLARBasedOnO2-$this->LAR_TT)*100/$this->ModelLARBasedOnO2)."% </b></li>"; 
	echo "</ul></div>";
	*/
	$this->GAPinfo = array(
					'mwhActual'=>$this->represent($this->energy / 1000),
					'mwhModel'=>$this->represent(($TotPE * $this->Period)/1000),
					'energyGap'=>$this->represent(($this->energy - ($TotPE * $this->Period))*100/($TotPE * $this->Period))
					) ;	
	//return $this->GAPinfo;
	}
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	/** Functions */
	
	function CalcGasProd($Value, $Period, $Inp2){ //Calculates gas flow to km3 from m3/h - Value is assumed to be in km3
    $v5 = ($Inp2=="per hour")?($this->Period / 1000):1;
    return $Value*$v5;
	}
	function CalcLiqProd($Value, $Media, $Period, $Inp1, $Inp2){ 
	/** Calculates liquid flow to km3 from m3/h depending on "to tank" or "from tank".Value is assumed to be in km3*/
    if ($Value == 0) return 0;
	if ($Inp2!="per hour") return $Value;
    $v1 = $this->GetSettingValue($Media."VariableLosses");
    $v2 = $this->GetSettingValue($Media."FixedLosses");
    $v3 = ($Inp1=="to tank")?(1 - $v1):1;
    $v4 = ($Inp1=="to tank")? $v2:0;
    return ($Value-$v4)*$this->Period/1000*$v3;
	}
	function Calc_FromTank($ToTank, $Media){    
    if($ToTank==0) return 0;
    $v1 = $this->GetSettingValue($Media."VariableLosses");
    $v2 = $this->GetSettingValue($Media."FixedLosses");
    if(!isset($v1)) $v1=0;
    if(!isset($v2)) $v2=0;
    $v3 = 1-$v1;
    return ($ToTank-$v2)*$v3;
	}
	function Calc_ToTank($FromTank, $Media){    
    if($FromTank==0) return 0;
    $v1 = $this->GetSettingValue($Media."VariableLosses");
    $v2 = $this->GetSettingValue($Media."FixedLosses");
    if(!isset($v1)) $v1 = 0;
    if(!isset($v2)) $v2 = 0;
    $v3 = 1 - $v1;
    return ($FromTank/$v3+$v2);
	}
	function Calc_RecFlowLimiterConst($AirFlow, $MaxAirFlow){
	$v1 = $this->GetSettingValue("RedAirFRecLimit");
	$v2 = $this->GetSettingValue("RedRecFLimit");
	$v3 = $this->GetSettingValue("RedRecFLimit");
    if(!isset($v1)) $v1 = 0;
    if(!isset($v2)) $v2 = 1;
	$k=(1-$v2)/(pow((1-$v1),0.95));
	if($AirFlow<1) $Calc_RecFlowLimiterConst=1;
	$Calc_RecFlowLimiterConst = ($AirFlow<=$this->MaxAirFlow)? 1-pow(($k*(1-($AirFlow/$this->MaxAirFlow))), 0.95):1;
	$max=max($Calc_RecFlowLimiterConst, 0.6);
	$min=min($Calc_RecFlowLimiterConst, 1.2);
	return $min;
	}
	function Calc_AirFlowLimiterConst($RecFlow, $MaxRecFlowSummer){
	$v1 = $this->GetSettingValue("RedAirFRecLimit");
	$v2 = $this->GetSettingValue("RedRecFLimit");
	if(!isset($v1)) return 0;
	if(!isset($v2)) $v2 = 0;
		$k = ($v1-1)/($v2-1);
		$m = 1-$k;
	if($RecFlow<1) return 1;
	return ($RecFlow<=$MaxRecFlowSummer)? $k*($RecFlow*1000/$MaxRecFlowSummer)+$m:1;
	}
	function Calc_MaxRecycleFlow($No,$RecFlowLimiterConst){
	$v1 =  $this->GetSettingValue("Recycle".$No."Maxsummer");
	$v2 =  $this->GetSettingValue("Recycle".$No."Maxwinter");
	$v3 =  $this->GetSettingValue("CWSummerTemp");
	$v4 =  $this->GetSettingValue("CWWinterTemp");
	return ((($v3-$v4)!=0)&&(($this->CWTemp-$v4)!=0))? $RecFlowLimiterConst*($v2-($v2-$v1)/($v3-$v4)*($this->CWTemp-$v4)):$RecFlowLimiterConst*$v2;
	}
	function Calc_MinRecycleFlow($No, $RecFlowLimiterConst){
	$v1 = $this->GetSettingValue("Recycle".$No."MINsummer");
	$v2 = $this->GetSettingValue("Recycle".$No."MINwinter");
	$v3 = $this->GetSettingValue("CWSummerTemp");
	$v4 = $this->GetSettingValue("CWWinterTemp");
	return ((($v3-$v4)!=0)&&(($this->CWTemp-$v4)!=0))? $RecFlowLimiterConst*($v2-($v2-$v1)/($v3-$v4)*($this->CWTemp-$v4)):$RecFlowLimiterConst *$v2;
	}
	function Calc_RecycleFlowForIC($No, $vPAir_S3){
    $v1 = $this->GetSettingValue("Recycle".$No."IC");
    $v2 = $this->GetSettingValue("GOX1ICFactor");
    $v3 = $this->GetSettingValue("GOX2ICFactor");
    $v4 = $this->GetSettingValue("GOX3ICFactor");
    $v18 = $this->GetSettingValue("GOX4ICFactor");
    $v5 = $this->GetSettingValue("GAN1ICFactor");
    $v6 = $this->GetSettingValue("GAN2ICFactor");
    $v7 = $this->GetSettingValue("GAN3ICFactor");
    $v19 = $this->GetSettingValue("GAN4ICFactor");
    $v8 = $this->GetSettingValue("GARICFactor");
    $v9 = $this->GetSettingValue("PairCompPartFactor");
    
    $v10 = $this->GOX1_S3*$v2;
    $v11 = $this->GOX2_S3*$v3;
    $v12 = $this->GOX3_S3*$v4;
    $v20 = $this->GOX4_S3*$v18;
    $v13 = $this->GAN1_S3*$v5;
    $v14 = $this->GAN2_S3*$v6;
    $v15 = $this->GAN3_S3*$v7;
    $v21 = $this->GAN4_S3*$v19;
    $v16 = $this->GAR_S3*$v8;
    $v17 = $vPAir_S3*$v9;
	return ($v1)?($v10+$v11+$v12+$v13+$v14+$v15+$v16+$v17+$v20+$v21)/$this->Period*1000:0;
	}
	function Calc_Liquefactionfactor($No, $pLiquidFlowRecycle, $CheckMin){
    $v7 = $this->GetSettingValue("LiquefactionFactorCWrelation");
    if(!isset($v7)) $v7 = 1;//Range("LiquefactionFactorCWrelation");
    $v1 = $this->GetSettingValue("LiquefactionFactor".$No."Formula_A");
    $v2 = $this->GetSettingValue("LiquefactionFactor".$No."Formula_B");
    $v3 = $this->GetSettingValue("LiquefactionFactor".$No."Formula_C");
    $v4 = $this->GetSettingValue("LiquefactionFactor".$No."Formula_D");
    $v5 = eval("return MaxLiqFactor".$No.";");
    $v6 = $this->GetSettingValue("RefCoolWTemp");
    $Calc_Liquefactionfactor = $v1*pow(($pLiquidFlowRecycle/1000),3)+$v2*pow(($pLiquidFlowRecycle / 1000),2)+$v3*($pLiquidFlowRecycle/1000)+$v4;
    $v8 = (1 + ($this->CWTemp - $v6) * $v7);
    $Calc_Liquefactionfactor = $Calc_Liquefactionfactor * $v8;
    if($pLiquidFlowRecycle==0) $Calc_Liquefactionfactor = $this->GetSettingValue("LiquefactionFactorDesign");
	if($CheckMin) $Calc_Liquefactionfactor = min($Calc_Liquefactionfactor, $v5);
	return $Calc_Liquefactionfactor;
	}
	function Calc_MaxLiquidFlowRecycle($No, $MaxRecycleFlow, $RecycleICFlow, $Liquefactionfactor){
    $v2 = max($this->TotLiq_TT, 0);
    $v3 = $this->GetSettingValue("Recycle".$No."MaxLiquidCap");
    $v4 = $this->GetSettingValue("ThermLossesLOXEq");
    $v6 = ($Liquefactionfactor>0) ?($MaxRecycleFlow - $RecycleICFlow) / $Liquefactionfactor - $v4 : 0;
    $v5 = ($Liquefactionfactor > 0)? min($v3, $v6):0;
    return max(0, $v5);
	}
	function Calc_LiquidFlowRecycle($No, $MaxLiquidFlowRecycle, $LiquidFlowOnOtherRecycle){
    $v1 = ($No==2)? $this->GetSettingValue("Recycle2Existing"):$this->GetSettingValue("Recycle1Existing");
    $v2 = $this->GetSettingValue("Recycle".$No."Priority");
    $v3 = ($No==2)? $this->GetSettingValue("Recycle1Existing"):$this->GetSettingValue("Recycle2Existing");
    $v4 = ($No==2)? $this->GetSettingValue("Rec1LiquidProd"):$this->GetSettingValue("Rec2LiquidProd");
     if($v1==1){ 
        if($v2==1){ 
            if($v3==1&&$v4 = 1){ 
                $Calc_LiquidFlowRecycle = min($this->TotLiq_TT, $MaxLiquidFlowRecycle);
            }else{
                $Calc_LiquidFlowRecycle = $this->TotLiq_TT;
            }
        }else{
            $Calc_LiquidFlowRecycle = $this->TotLiq_TT - $LiquidFlowOnOtherRecycle;
        }
    }else{
        $Calc_LiquidFlowRecycle = 0;
    }
	return $Calc_LiquidFlowRecycle; 
	}
	function Calc_ModelRecycle($No, $LiquidFlowRecycle, $RecycleFlowForIC, $Liquefactionfactor){
	$v1 = $this->GetSettingValue("ThermLossesLOXEq");
	$v2 = $this->GetSettingValue("Recycle".$No."Priority");
	$v3 = $this->GetSettingValue("Recycle".$No."Existing");
	if($LiquidFlowRecycle + $RecycleFlowForIC==0){
		return 0;
	}else{
		$v17 = ($LiquidFlowRecycle + $v1 * $v2) * $Liquefactionfactor;
		return ($v17 + $RecycleFlowForIC) * $this->Period / 1000 * $v3;
	}
	}
	function Calc_ModelGasCaseTime($ModelRecycle1, $ModelRecycle2, $Period, $MinRecycle1Flow, $Liquefactionfactor1, $MinRecycle2Flow, $Liquefactionfactor2, $GasCaseMax, $LINInjectionMax, $LPTurbineExists){
	$v1 = $this->GetSettingValue("Recycle1Existing");
	$v2 = $this->GetSettingValue("Recycle2Existing");
	$v4 = $LPTurbineExists;
	$v5 = LINInjectionExists;
	if($v4==0&&$v5==0) return 0;
	if($v1==0&&$v2==0&&$v4==1) return 1;
	$v1 = $this->GetSettingValue("Recycle1Priority");
	$v2 = $this->GetSettingValue("LPTurbineCP");
	$LINCp = 0.16 * 0.93; 
	$v8 = $LINCp;
	if($v1){ 
	 if($ModelRecycle1 < (StopToStartLPTurbine * $this->Period / 1000)){ 
		$v10 = $ModelRecycle1; 
		$v11 = $Liquefactionfactor1; 
		$v9 = $MinRecycle1Flow;
		if($Liquefactionfactor1==0) $Liquefactionfactor1 = 1;
		$kWNeededBelowMinRecycle = ($ModelRecycle1 / $this->Period * 1000 - $MinRecycle1Flow) / $Liquefactionfactor1 * 0.16 ;
		$kWGasCaseBelowMinRecycle = ($v2 * $GasCaseMax * $v4 + $LINCp * $LINInjectionMax * $v5 - $MinRecycle1Flow / $Liquefactionfactor1 * 0.16); 
		$v6 = min(-0.1, $kWNeededBelowMinRecycle);  
		$v7 = min(-0.1, $kWGasCaseBelowMinRecycle);  
		$v3 = $v6 / $v7;
		$v3 = max(0, $v3);
		$v3 = min(1, $v3);
	 }else{
		$v3 = 0;
	 }
	}else{ 
		 if($ModelRecycle2 < StopToStartLPTurbine){
			$v10 = $ModelRecycle2;
			$v11 = $Liquefactionfactor2; 
			$v9 = $MinRecycle1Flow;
			if($Liquefactionfactor2==0) $Liquefactionfactor2 = 1;
			$kWNeededBelowMinRecycle = ($ModelRecycle2 / $this->Period * 1000 - $MinRecycle2Flow) / $Liquefactionfactor1 * 0.16; 
			$kWGasCaseBelowMinRecycle = ($v2 * $GasCaseMax * $v4 + $LINCp * $LINInjectionMax * $v5 - $MinRecycle2Flow / $Liquefactionfactor1 * 0.16); 
			$v6 = $kWNeededBelowMinRecycle; 
			$v7 = $kWGasCaseBelowMinRecycle; 
			$v3 = $kWNeededBelowMinRecycle / $kWGasCaseBelowMinRecycle; 
			$v3 = max(0, $v3);
			$v3 = min(1, $v3);
		 }else{
			$v3 = 0;
		 }
	  }
	}

	function Calc_ModelLPTurbine($ModelGasCaseTime, $GasCaseMax){
		return $ModelGasCaseTime * $this->Period / 1000 * $GasCaseMax;
	}
	
	function Calc_RecyclePower($No, $ModelRecycle, $ModelGasCaseTime, $Liquefactionfactor, $MinRecycleFlow, $MaxRecycleFlow, $ModelRecycleFlow, $RecyclePowerCorrection){
	$v1 = $this->GetSettingValue("Rec".$No."CompPCFormula_A");
    $v2 = $this->GetSettingValue("Rec".$No."CompPCFormula_B");
    $v3 = $this->GetSettingValue("Rec".$No."CompPCFormula_C");
    $v4 = $this->GetSettingValue("Rec".$No."CompPCFormula_D");
    $v5 = $this->GetSettingValue("RefCoolWTemp");
    $v6 = $this->GetSettingValue("RefInlAirTemp");
    $vModelRecycleFlow = max($MinRecycleFlow / 1000, $ModelRecycleFlow);
    $RecyclePowerCorrection = 1 / (4.1 / (1 + 3 * (273.15 + $v5 + 10) / (273.15 + $v6)) / (4.1 / (1 + 3 * (273.15 + $this->CWTemp + 10) / (273.15 + $v6))));
    $Calc_RecyclePower = ($v1 * pow($vModelRecycleFlow,3) + $v2 * pow($vModelRecycleFlow,2) + $v3 * $vModelRecycleFlow + $v4) / $RecyclePowerCorrection;
    $Calc_RecyclePower = $Calc_RecyclePower * (1 - $ModelGasCaseTime);
    return $Calc_RecyclePower;
	}
	function Calc_ModelLPTurbineFlow($ModelAir, $Period){
    $v1 = $this->GetSettingValue("AirTurbineFlowFormula_A");
    $v2 = $this->GetSettingValue("AirTurbineFlowFormula_B");
    $v3 = $this->GetSettingValue("AirTurbineFlowFormula_C");
    $v4 = $this->GetSettingValue("AirTurbineFlowFormula_D");
    $LPTurbineFlow = $v1 * pow(($ModelAir / $this->Period),3) + $v2 * pow(($ModelAir / $this->Period),2) + $v3 * $ModelAir / $this->Period + $v4;
    return $LPTurbineFlow;
    }
	function Calc_ModelLPTurbinekW($LPTurbineFlow){
    $v1 = $this->GetSettingValue("AirTurbinePCFormula_A");
    $v2 = $this->GetSettingValue("AirTurbinePCFormula_B");
    $v3 = $this->GetSettingValue("AirTurbinePCFormula_C");
    $v4 = $this->GetSettingValue("AirTurbinePCFormula_D");
    $LPTurbinekW = $v1 * pow(($LPTurbineFlow / 1000),3) + $v2 * pow(($LPTurbineFlow / 1000),2) + $v3 * $LPTurbineFlow / 1000 + $v4;
    return $LPTurbinekW;
	}
	function Calc_ModelLINInjection2($TotLiq_TT_kW, $LPTurbinekW, $CWTemp, $LIN, $Period, $ThermalLosseskW, $LINInjectionkW){    
    $v1 = $this->GetSettingValue("LiquefactionFactorCWrelation");
    $v2 = $this->GetSettingValue("RefCoolWTemp");
    if (!isset($v1)) $v1 = 1; //Range("LiquefactionFactorCWrelation");
    $v3 = $LINInjectionkW * 3600 / 570.8; 
    $v4 = $v3 / 0.93; 
    $v5 = (1 + ($this->CWTemp - $v2) * $v1); 
    return $v4 * $v5;
	}
	function Calc_TotLiqTT_kW($TotLiq_TT, $LIN, $Period){
    $Totliq_TTkW = ($this->TotLiq_TT - $this->LIN * 1000 / $this->Period * 0.93) * 570.8 / 3600;
    return $Totliq_TTkW;
	}
	function Calc_RecycleFlow($ModelRecycleRestricted){
    return $ModelRecycleRestricted / $this->Period;
	}
	function Calc_ModelRecycleRestricted($No, $LPTurbineAutomatic, $ModelRecycle, $ModelLPTurbine, $MaxRecycle1Flow, $MinRecycleFlow, $Liquefactionfactor, $ModelLINInjection){
    $v1 = $this->GetSettingValue("LPTurbineCP");
    $v2 = $this->GetSettingValue("GasCaseExisting");
    $v4 = $this->GetSettingValue("LINInjection");
    $v15 = 0;
    $v16 = 0;
    if($ModelRecycle==0) {
        return 0;
    }else{
        if($LPTurbineAutomatic==1){
            $v15 = $v2 * $ModelLPTurbine * $v1 / 0.16 * $Liquefactionfactor;
            $v16 = $v4 * $ModelLINInjection * 0.93 * $Liquefactionfactor;
            $v3 = $ModelRecycle - $v15 - $v16;
            
        }else{
            $v3 = max($MinRecycleFlow * $this->Period / 1000, $ModelRecycle);
        }
        return max($v3, 0);
    }
	}
	function Calc_ModelSepBypass($pLIN_TT, $LINinjection, $FeedFlow, $ModelAir, $LPTurbine, $GasCase){
	if($ModelAir < 1) return 0;
    if($GasCase==2) $pLIN_TT = 0;
    $v1 = $this->GetSettingValue("GANUsingMPGAN1") * $this->GAN1_S1;
    $v2 = $this->GetSettingValue("GANUsingMPGAN2") * $this->GAN2_S1;
    $v3 = $this->GetSettingValue("GANUsingMPGAN3") * $this->GAN3_S1;
    $v4 = $this->GetSettingValue("GANUsingMPGAN4") * $this->GAN4_S1;
    $v5 = $this->GetSettingValue("RecycleMediaNo");
    $v6 = $this->GetSettingValue("GOX1ICFactor") * $this->GOX1_S1;
    $v7 = $this->GetSettingValue("GOX2ICFactor") * $this->GOX2_S1;
    $v8 = $this->GetSettingValue("GOX3ICFactor") * $this->GOX3_S1;
    $v9 = $this->GetSettingValue("GOX4ICFactor") * $this->GOX4_S1;
    $v10 = $this->GetSettingValue("GAN1ICFactor") * $this->GAN1_S1;
    $v11 = $this->GetSettingValue("GAN2ICFactor") * $this->GAN2_S1;
    $v12 = $this->GetSettingValue("GAN3ICFactor") * $this->GAN3_S1;
    $v13 = $this->GetSettingValue("GAN4ICFactor") * $this->GAN4_S1;
    $v14 = $this->GetSettingValue("GARICFactor") * $this->GAR_S1;    
    $v5 = ($v5==1)?($v6 + $v7 + $v8 + $v9 + $v10 + $v11 + $v12 + $v13 + $v14) * 0.4:0;
	return max(-0.15, (($pLIN_TT - $LINinjection) * 1.08 + $v1 + $v2 + $v3 + $v4 + $v5 - $FeedFlow + $LPTurbine) / $ModelAir);
	}
	function Calc_ModelArRecovTech($ModelSepBypass){
    $v1 = $this->GetSettingValue("ArRecoveryFormula_A");
    $v2 = $this->GetSettingValue("ArRecoveryFormula_B");
    $v3 = $this->GetSettingValue("ArRecoveryFormula_C");
    $v4 = $this->GetSettingValue("ArRecoveryFormula_D");
    $v5 = $v1 * pow($ModelSepBypass,3) + $v2 * pow($ModelSepBypass,2) + $v3 * $ModelSepBypass + $v4;
	return min(1, $v5);
	}
	function Calc_ModelO2RecovTech($ModelSepBypass){
    $v1 = $this->GetSettingValue("O2RecoveryFormula_A");
    $v2 = $this->GetSettingValue("O2RecoveryFormula_B");
    $v3 = $this->GetSettingValue("O2RecoveryFormula_C");
    $v4 = $this->GetSettingValue("O2RecoveryFormula_D");
    $v5 = $v1 * pow($ModelSepBypass,3) + $v2 * pow($ModelSepBypass,2) + $v3 * $ModelSepBypass + $v4;
	return min(1, $v5);
	}
	function Calc_ModelArRecovProd($ModelRecovTech, $ModelAir){
    if($ModelAir==0) {
        return 0;
    }else{
        $v1 = $this->GetSettingValue("LARVariableLosses") * ($this->LAR_TT + $this->HLAR_TT + $this->CLAR_TT); 
        $v2 = $this->GetSettingValue("LARFixedLosses") * $this->Period / 1000;
        $Calc_ModelArRecovProd = $ModelRecovTech - ($v1 + $v2) / ($ModelAir * 0.00934);
        if($Calc_ModelArRecovProd > 1) $Calc_ModelArRecovProd = 1;
        if($Calc_ModelArRecovProd < 0) $Calc_ModelArRecovProd = 0;
		}
		return $Calc_ModelArRecovProd;
	}
	function Calc_ModelO2RecovProd($ModelAir){
    return ($ModelAir==0)? 0: $this->TotO2_FT / ($ModelAir * 0.2095);
	}
	function Calc_MaxAirFlow(){
    $v1 = $this->GetSettingValue("AirMAXsummer");
    $v2 = $this->GetSettingValue("AirMAXwinter");
    $v3 = $this->GetSettingValue("AirSummerTemp");
    $v4 = $this->GetSettingValue("AirWinterTemp");
    $v5 = $this->GetSettingValue("CWSummerTemp");
    $v6 = $this->GetSettingValue("CWWinterTemp");
	if(($v3 - $v4 + $v5 - $v6)!=0&&($this->AirTemp - $v4 + $this->CWTemp - $v6)!=0){
		return $v2 - ($v2 - $v1) / ($v3 - $v4 + $v5 - $v6) * ($this->AirTemp - $v4 + $this->CWTemp - $v6);
	}else{
		return $v2;
		}
	}
	function Calc_MinAirFlow(){
    $v1 = $this->GetSettingValue("AirMINsummer");
    $v2 = $this->GetSettingValue("AirMINwinter");
    $v3 = $this->GetSettingValue("AirSummerTemp");
    $v4 = $this->GetSettingValue("AirWinterTemp");
    $v5 = $this->GetSettingValue("CWSummerTemp");
    $v6 = $this->GetSettingValue("CWWinterTemp");
	if (($v3 - $v4 + $v5 - $v6)!=0&&($this->AirTemp - $v4 + $this->CWTemp - $v6)!=0){
		return $v2 - ($v2 - $v1) / ($v3 - $v4 + $v5 - $v6) * ($this->AirTemp - $v4 + $this->CWTemp - $v6);
	}else{
		return $v2;
		}
	}
	function Calc_ModelAirO2($ModelO2RecovTech){
    return (GOXforArgon + $this->LOX_TT + $this->GOX1_S1 + $this->GOX2_S1 + $this->GOX3_S1 + $this->GOX4_S1) / ($ModelO2RecovTech * 0.2095);
	}
	function Calc_ModelAirAr($ModelArRecovTech){
    return ($this->LAR_TT + 0.97 * $this->CLAR_TT + $this->HLAR_TT + $this->GAR_S1) / ($ModelArRecovTech * 0.00934);
	}
	function Calc_ModelAirN2(){
    $v1 = $this->GetSettingValue("GAN1EC");
    $v2 = $this->GetSettingValue("GAN2EC");
    $v3 = $this->GetSettingValue("GAN3EC");
    $v4 = $this->GetSettingValue("GAN4EC");
    $v5 = $this->GetSettingValue("GANForMSA");
    $v6 = $this->GetSettingValue("MinGANLPCTop");
    $v7 = array();
	$v7[]= $this->GetSettingValue("GANCW01");
    $v7[] = $this->GetSettingValue("GANCW2");
    $v7[] = $this->GetSettingValue("GANCW3");
    $v7[] = $this->GetSettingValue("GANCW4");
    $v7[] = $this->GetSettingValue("GANCW5");
    $v7[] = $this->GetSettingValue("GANCW6");
    $v7[] = $this->GetSettingValue("GANCW7");
    $v7[] = $this->GetSettingValue("GANCW8");
    $v7[] = $this->GetSettingValue("GANCW9");
    $v7[] = $this->GetSettingValue("GANCW10");
    $v7[] = $this->GetSettingValue("GANCW11");
    $v7[] = $this->GetSettingValue("GANCW12");
    
    $v19 = ($this->LIN_TT + $this->HLIN_TT + $this->GAN1_S1 * (1 - $v1) + $this->GAN2_S1 * (1 - $v2) + $this->GAN3_S1 * (1 - $v3) + $this->GAN4_S1 * (1 - $v4) + $v5 * $this->Period / 1000);
    $v20 = $v6 * $this->Period / 1000;
    $v21 = $this->GAN1_S1 * $v1 + $this->GAN2_S1 * $v2 + $this->GAN3_S1 * $v3 + $this->GAN4_S1 * $v4;
    //if($this->MonthNumber==0) $this->MonthNumber = 5;
    $v22 = $v7[$this->MonthNumber-1] * $this->Period / 1000;
    $v23 = max($v20, $v21 + $v22);
    return 1 / 0.78084 * ($v19 + $v23);
	}
	function Calc_ModelAir($MinAirFlow, $MaxAirFlow, $ModelAirN2, $ModelAirAr, $ModelAirO2, $ModelAirRecycle, $ModelRecycle1Restricted, $ModelRecycle2Restricted){
    $v10 = "Yes"; /* Air based on O2, N2 or Ar*/
    $v11 = "Yes"; /* Ar based on O2 and N2*/
    
	$v12 = ($v10=="Yes")? $ModelAirN2:0;
	$v13 = ($v11=="Yes")? $ModelAirAr:0;
	 
	$v4 = max($v12, $v13, $ModelAirRecycle, $ModelAirO2, ($this->MinAirFlow * $this->Period / 1000));
	return min($v4, ($this->MaxAirFlow * 1.4 * $this->Period / 1000));
	}
	function Calc_ModelAirPower($ModelAirFlow,$AirPowerCorrection, $AirPowerCorrection1, $AirPowerCorrection2){
    $v1 = $this->GetSettingValue("AirCompPCFormula_A");
    $v2 = $this->GetSettingValue("AirCompPCFormula_B");
    $v3 = $this->GetSettingValue("AirCompPCFormula_C");
    $v4 = $this->GetSettingValue("AirCompPCFormula_D");
    $v5 = RefCoolWTemp;
    $v6 = RefInlAirTemp;
    $v7 = RefRelHumAirComp;
        
    $AirPowerCorrection1 = ((273.15 + $this->AirTemp) / (273.15 + $v6) + 3) / 4 * (1 + $this->RH * 2.98146E-50 * pow(($this->AirTemp + 273.15),19.410194182)) / (1 + $v7 * 2.98146E-50 * pow(($v6 + 273.15),19.410194182));
    $AirPowerCorrection2 = 4.1 / (1 + 3 * (273.15 + $v5 + 10)/ (273.15 + $v6)) / (4.1 / (1 + 3 * (273.15 + $this->CWTemp + 10) / (273.15 + $v6)));
    $AirPowerCorrection = $AirPowerCorrection1 * $AirPowerCorrection2;
    return ($v1 * pow($ModelAirFlow,3) + $v2 * pow($ModelAirFlow,2) + $v3 * $ModelAirFlow + $v4) * $AirPowerCorrection;
	}	
	function Calc_ModelFeedPower($ModelFeedFlow){
	if($this->Period==0) return 0;
    $v1 = $this->GetSettingValue("FeedCompPCFormula_A");
    $v2 = $this->GetSettingValue("FeedCompPCFormula_B");
    $v3 = $this->GetSettingValue("FeedCompPCFormula_C");
    $v4 = $this->GetSettingValue("FeedCompPCFormula_D");
    $v5 = RefCoolWTemp;
    $v6 = RefInlAirTemp;
    $v7 = RefRelHumAirComp;
    $v11 = 1 / (4.1 / (1 + 3 * (273.15 + $v5 + 10) / (273.15 + $v6)) / (4.1 / (1 + 3 * (273.15 + $this->CWTemp + 10) / (273.15 + $v6))));
    $FeedPowerCorrection = $v11;
    return ($v1 * pow($ModelFeedFlow,3) + $v2 * pow($ModelFeedFlow,2) + $v3 * $ModelFeedFlow + $v4) / $FeedPowerCorrection;
	}
	function Calc_ModelProdCompressorPower($Media, $Product){
	if($this->Period==0) return 0;
    $v1 = $this->GetSettingValue($Media."PCFormula_A");
    $v2 = $this->GetSettingValue($Media."PCFormula_B");
    $v3 = $this->GetSettingValue($Media."PCFormula_C");
    $v4 = $this->GetSettingValue($Media."PCFormula_D");
    $v5 = RefCoolWTemp;
    $v6 = RefInlAirTemp;
    $v7 = RefRelHumAirComp;
    $v8 = $Product / $this->Period;
    $v11 = 1 / (4.1 / (1 + 3 * (273.15 + $v5 + 10) / (273.15 + $v6)) / (4.1 / (1 + 3 * (273.15 + $this->CWTemp + 10) / (273.15 + $v6))));
    $PowerCorrection = $v11;
    return ($v1 * pow($v8,3) + $v2 * pow($v8,2) + $v3 * $v8 + $v4) / $PowerCorrection;
	}
	function RecycleFlowForIC_S1($No){
    $v1 = $this->GetSettingValue("Recycle".$No."IC");
    $v2 = $this->GetSettingValue("GOX1ICFactor");
    $v3 = $this->GetSettingValue("GOX2ICFactor");
    $v4 = $this->GetSettingValue("GOX3ICFactor");
    $v18 = $this->GetSettingValue("GOX4ICFactor");
    $v5 = $this->GetSettingValue("GAN1ICFactor");
    $v6 = $this->GetSettingValue("GAN2ICFactor");
    $v7 = $this->GetSettingValue("GAN3ICFactor");
    $v19 = $this->GetSettingValue("GAN4ICFactor");
    $v8 = $this->GetSettingValue("GARICFactor");
    $v9 = $this->GetSettingValue("PairCompPartFactor");
	if($v1==1){
		$v10 = max($this->GOX1_S1, $this->GOX1_S3) * $v2;
		$v11 = max($this->GOX2_S1, $this->GOX2_S3) * $v3;
		$v12 = max($this->GOX3_S1, $this->GOX3_S3) * $v4;
		$v20 = max($this->GOX4_S1, $this->GOX4_S3) * $v18;
		$v13 = max($this->GAN1_S1, $this->GAN1_S3) * $v5;
		$v14 = max($this->GAN2_S1, $this->GAN2_S3) * $v6;
		$v15 = max($this->GAN3_S1, $this->GAN3_S3) * $v7;
		$v21 = max($this->GAN4_S1, $this->GAN4_S3) * $v19;
		$v16 = max($this->GAR_S1, $this->GAR_S3) * $v8;
		$v17 = $this->PAir_S3 * $v9;
		return ($v10 + $v11 + $v12 + $v13 + $v14 + $v15 + $v16 + $v17 + $v20 + $v21) * 1000 / $this->Period;
		}else{
		return 0;
		}
	}
	function RecycleFlowForIC_S3($No){
    $v1 = $this->GetSettingValue("Recycle".$No."IC");
    $v2 = $this->GetSettingValue("GOX1ICFactor");
    $v3 = $this->GetSettingValue("GOX2ICFactor");
    $v4 = $this->GetSettingValue("GOX3ICFactor");
    $v18 = $this->GetSettingValue("GOX4ICFactor");
    $v5 = $this->GetSettingValue("GAN1ICFactor");
    $v6 = $this->GetSettingValue("GAN2ICFactor");
    $v7 = $this->GetSettingValue("GAN3ICFactor");
    $v19 = $this->GetSettingValue("GAN4ICFactor");
    $v8 = $this->GetSettingValue("GARICFactor");
    $v9 = $this->GetSettingValue("PairCompPartFactor");
	if($v1==1){ 
		$v10 = $this->GOX1_S3 * $v2;
		$v11 = $this->GOX2_S3 * $v3;
		$v12 = $this->GOX3_S3 * $v4;
		$v20 = $this->GOX4_S3 * $v18;
		$v13 = $this->GAN1_S3 * $v5;
		$v14 = $this->GAN2_S3 * $v6;
		$v15 = $this->GAN3_S3 * $v7;
		$v21 = $this->GAN4_S3 * $v19;
		$v16 = $this->GAR_S3 * $v8;
		$v17 = $this->PAir_S3 * $v9;
		return ($v10 + $v11 + $v12 + $v13 + $v14 + $v15 + $v16 + $v17 + $v20 + $v21) * 1000 / $this->Period;
		}else{
		return 0;
		}
	}



	
	//***********************************************************
	// incomplete ...
	//***********************************************************	
	
	
	
	
	
	
	
	function Calc_Spezi($TotPC, $LIN, $HLIN, $LAR, $CLAR, $HLAR, $KrXe, $GOX1_S3, $GOX2_S3, $GOX3_S3, $GAN1_S3, $GAN2_S3, $GAN3_S3, $GAR_S3, $PAir_S3, $NeHe_S3){
 
    $v1 = $this->GetSettingValue("SpeziFactorLOX") * $this->LOX;
    $v2 = $this->GetSettingValue("SpeziFactorHLOX") * $this->HLOX;
    $v3 = $this->GetSettingValue("SpeziFactorHHLOX") * $this->HHLOX;
    $v4 = $this->GetSettingValue("SpeziFactorLIN") * $LIN;
    $v5 = $this->GetSettingValue("SpeziFactorHLIN") * $HLIN;
    $v6 = $this->GetSettingValue("SpeziFactorLAR") * $LAR;
    $v7 = $this->GetSettingValue("SpeziFactorCLAR") * $CLAR;
    $v8 = $this->GetSettingValue("SpeziFactorHLAR") * $HLAR;
    $v9 = $this->GetSettingValue("SpeziFactorKr_Xe") * $KrXe;
    $v10 = $this->GetSettingValue("SpeziFactorGOX1") * $GOX1_S3;
    $v11 = $this->GetSettingValue("SpeziFactorGOX2") * $GOX2_S3;
    $v12 = $this->GetSettingValue("SpeziFactorGOX3") * $GOX3_S3;
    $v13 = $this->GetSettingValue("SpeziFactorGAN1") * $GAN1_S3;
    $v14 = $this->GetSettingValue("SpeziFactorGAN2") * $GAN2_S3;
    $v15 = $this->GetSettingValue("SpeziFactorGAN3") * $GAN3_S3;
    $v16 = $this->GetSettingValue("SpeziFactorGAR") * $GAR_S3;
    $v17 = $this->GetSettingValue("SpeziFactorPAir") * $PAir_S3;
    $v18 = $this->GetSettingValue("SpeziFactorNe_He") * $NeHe_S3;
    $v19 = 1;
    
    $v20 = $v1 + $v2 + $v3 + $v4 + $v5 + $v6 + $v7 + $v8 + $v9 + $v10 + $v11 + $v12 + $v13 + $v14 + $v15 + $v16 + $v17 + $v18;
    return ($v20 <= 0)? 0: $TotPC * $this->Period / 1000 / ($v20 * $v19);
	}
	function GetCWTemp($MonthNumber){
	switch ($this->MonthNumber){
        Case 0:
        return $this->GetSettingValue("TempCW1");
		break;
        Case 1:
        return $this->GetSettingValue("TempCW2");
		break;
        Case 2:
        return $this->GetSettingValue("TempCW3");
		break;
        Case 3:
        return $this->GetSettingValue("TempCW4");
		break;
        Case 4:
        return $this->GetSettingValue("TempCW5");
		break;
        Case 5:
        return $this->GetSettingValue("TempCW6");
		break;
        Case 6:
        return $this->GetSettingValue("TempCW7");
		break;
        Case 7:
        return $this->GetSettingValue("TempCW8");
		break;
        Case 8:
        return $this->GetSettingValue("TempCW9");
		break;
        Case 9:
        return $this->GetSettingValue("TempCW10");
		break;
        Case 10:
        return $this->GetSettingValue("TempCW11");
		break;
        Case 11:
        return $this->GetSettingValue("TempCW12");
		break;
		}
	}
	function GetAirTemp($MonthNumber){
		switch ($this->MonthNumber){
        Case 0:
        return $this->GetSettingValue("TempOut1");
		break;
        Case 1:
        return $this->GetSettingValue("TempOut2");
		break;
        Case 2:
        return $this->GetSettingValue("TempOut3");
		break;
        Case 3:
        return $this->GetSettingValue("TempOut4");
		break;
        Case 4:
        return $this->GetSettingValue("TempOut5");
		break;
        Case 5:
        return $this->GetSettingValue("TempOut6");
		break;
        Case 6:
        return $this->GetSettingValue("TempOut7");
		break;
        Case 7:
        return $this->GetSettingValue("TempOut8");
		break;
        Case 8:
        return $this->GetSettingValue("TempOut9");
		break;
        Case 9:
        return $this->GetSettingValue("TempOut10");
		break;
        Case 10:
        return $this->GetSettingValue("TempOut11");
		break;
        Case 11:
        return $this->GetSettingValue("TempOut12");
		break;
		}
	}
	function GetRH($MonthNumber){
	switch ($this->MonthNumber){
        Case 0:
        return $this->GetSettingValue("RH1");
		break;
        Case 1:
        return $this->GetSettingValue("RH2");
		break;
        Case 2:
        return $this->GetSettingValue("RH03");
		break;
        Case 3:
        return $this->GetSettingValue("RH4");
		break;
        Case 4:
        return $this->GetSettingValue("RH5");
		break;
        Case 5:
        return $this->GetSettingValue("RH6");
		break;
        Case 6:
        return $this->GetSettingValue("RH7");
		break;
        Case 7:
        return $this->GetSettingValue("RH8");
		break;
        Case 8:
        return $this->GetSettingValue("RH9");
		break;
        Case 9:
        return $this->GetSettingValue("RH10");
		break;
        Case 10:
        return $this->GetSettingValue("RH11");
		break;
        Case 11:
        return $this->GetSettingValue("RH12");
		break;
		}
	}
	function Calc_CommonPowerConsumers($Period, $TotPC, $AirPower, $Recycle1Power, $Recycle2Power, $FeedPower, $GOX1Power, $GOX2Power, $GOX3Power, $GOX4Power, $GAN1Power, $GAN2Power, $GAN3Power, $GAN4Power, $PAirPower, $OtherPower){
    $v1 = $this->GetSettingValue("PairCompPartFactor");
    //$v2 = GetSettingRangeSum("CommonPowerConsumers"); /** ATTENTION HERE */
    $v2 = $this->GetSettingValue("CommonPowerConsumers"); /** ATTENTION HERE */
    return ($TotPC - $AirPower - $Recycle1Power - $Recycle2Power - $FeedPower - $GOX1Power - $GOX2Power - $GOX3Power - $GOX4Power - $GAN1Power - $GAN2Power - $GAN3Power - $GAN4Power - $PAirPower - ($OtherPower - $v2)) * $this->Period / 1000;
	}
	function Calc_SpecSep($History , $plantmonthloop, $Period, $TotPC, $Air, $AirPower, $PAir_S3, $LiquidFlowRecycle1, $Liquefactionfactor1, $LiquidFlowRecycle2, $Liquefactionfactor2, $Recycle1FlowForIC_S1, $Recycle2FlowForIC_S1, $CommonPowerConsumers, $TotO2_FT, $TotAr_FT, $Recycle1Power, $Recycle2Power, $FeedPower){
    $v1 = $this->GetSettingValue("PairFromAirComp");
    $v2 = $this->GetSettingValue("RefUnitSepkW");
    $v3 = $this->GetSettingValue("DCACWCkW");
    $v4 = $this->GetSettingValue("MSkW");
    $v5 = $this->GetSettingValue("PumpLOXcirc");
    $v6 = $this->GetSettingValue("PumpEff");
    $v7 = $this->GetSettingValue("TotThermLosseskW");
    $v8 = $this->GetSettingValue("ThermLossesLOXEq");
    $v9 = $this->GetSettingValue("ThermLossesIns");
    $v10 = $this->GetSettingValue("ThermLossesMHE");
    $v11 = $this->GetSettingValue("Recycle1Priority");
    $v12 = $this->GetSettingValue("Recycle2Priority");
    $Rec1FlowForLiq = $LiquidFlowRecycle1 * $Liquefactionfactor1;
    $Rec1FlowForThermalLosses = $v8 * $v11 * $Liquefactionfactor1;
    $Rec1FlowTotal_S1 = $Rec1FlowForLiq + $Rec1FlowForThermalLosses + $Recycle1FlowForIC_S1;
    $Rec2FlowForLiq = $LiquidFlowRecycle2 * $Liquefactionfactor2;
    $Rec2FlowForThermalLosses = $v8 * $v12 * $Liquefactionfactor2;
    $Rec2FlowTotal_S1 = $Rec2FlowForLiq + $Rec2FlowForThermalLosses + $Recycle2FlowForIC_S1;

    if($History){
    /** vCol11 = Blad7.Range("CalcStartMonthColumn").Column
        Cells(285, vCol11 + plantmonthloop) = Rec1FlowForLiq
        Cells(286, vCol11 + plantmonthloop) = Rec1FlowForThermalLosses
        Cells(289, vCol11 + plantmonthloop) = Rec1FlowTotal_S1
        Cells(291, vCol11 + plantmonthloop) = Rec2FlowForLiq
        Cells(292, vCol11 + plantmonthloop) = Rec2FlowForThermalLosses
        Cells(295, vCol11 + plantmonthloop) = Rec2FlowTotal_S1
    */
	}
 
    if($this->Period<0.5||$Air==0||$TotPC==0) {
        return 0;
    }else{
        $v18 = (($AirPower / 1000 * $this->Period) * $Air / ($Air + $v1 * $PAir_S3)) + (($v2 + $v3 + $v4 + $v5) / 1000 * $this->Period);        
        $v19 = ($Rec1FlowTotal_S1==0) ? 0:$Recycle1Power / 1000 * $this->Period * $Rec1FlowForThermalLosses / $Rec1FlowTotal_S1;
        $v20 = ($Rec2FlowTotal_S1==0) ? 0 : $Recycle2Power / 1000 * $this->Period * $Rec2FlowForThermalLosses / $Rec2FlowTotal_S1;
        $v21 = $v18 + ($v19 + $v20) * (($v5 * (1 - $v6) + $v9 + $v10) / $v7);
        $v22 = (($this->TotO2_FT + $TotAr_FT)==0)? 1: 1 / (1 - ($CommonPowerConsumers / ($TotPC * $this->Period / 1000))) / ($this->TotO2_FT + $TotAr_FT);
        return $v21 * $v22;
		}
	}
	function Calc_SpecLiq($Period, $ModelFeedPower, $TotPC, $TotLiq_FT, $ModelAir, $ModelAirPower, $PAir_S3, $LiquidFlowRecycle1, $Liquefactionfactor1, $LiquidFlowRecycle2, $Liquefactionfactor2, $Recycle1FlowForIC, $Recycle2FlowForIC, $CommonPowerConsumers, $ModelRecycle1Power, $ModelRecycle2Power){
    $v1 = $this->GetSettingValue("RefUnitLiqkW");
    $v8 = $this->GetSettingValue("ThermLossesLOXEq");
    $v9 = $this->GetSettingValue("ThermLossesIns");
    $v11 = $this->GetSettingValue("Recycle1Priority");
    $v12 = $this->GetSettingValue("Recycle2Priority");
    $Rec1FlowForLiq = $LiquidFlowRecycle1 * $Liquefactionfactor1;
    $Rec1FlowForThermalLosses = $v8 * $v11 * $Liquefactionfactor1;
    $Rec1FlowTotal = $Rec1FlowForLiq + $Rec1FlowForThermalLosses + $Recycle1FlowForIC;
    $Rec2FlowForLiq = $LiquidFlowRecycle2 * $Liquefactionfactor2;
    $Rec2FlowForThermalLosses = $v8 * $v12 * $Liquefactionfactor2;
    $Rec2FlowTotal = $Rec2FlowForLiq + $Rec2FlowForThermalLosses + $Recycle2FlowForIC;
    if($this->Period<0.5||$TotPC==0) {
        return 0;
	}else{
        $v18 = ($v1 + $ModelFeedPower) / 1000 * $this->Period;        
        $v19 = ($Rec1FlowTotal==0)? 0 : $ModelRecycle1Power / 1000 * $this->Period * $Rec1FlowForLiq / $Rec1FlowTotal;
        $v20 = ($Rec2FlowTotal==0)? 0 : $ModelRecycle2Power / 1000 * $this->Period * $Rec2FlowForLiq / $Rec2FlowTotal;        
        $v21 = (1 - $CommonPowerConsumers / ($TotPC * $this->Period / 1000));
        return ($TotLiq_FT>0) ? ($v18 + $v19 + $v20) / $v21 / ($TotLiq_FT * $this->Period / 1000):0;
		}
	}

	
	//***********************************************************
	// GENERAL FUNCTIONS getSETTINGS
	//***********************************************************
	
	function GetSettingValue($pRange){
	@$vValue = $this->Settings[$pRange];
    if($vValue=="True") $vValue=1;
    if($vValue=="False") $vValue=0;
    if(!isset($vValue)) return 0;
    return $vValue;
	}
	function getSettings(){
	if (!isset($this->PlantName)) die("Plant not set or invalid name");
	$strSQL = "SELECT paramID,".$this->PlantName." FROM rsa_portal.cryo__PMSettings";
	$connector = new DbConnector(); 
	$settings = $connector->query($strSQL);
	//if(!$settings) die ("error de datos");
	if(!$settings) die ("Error - this plant does not seem to exists - verify the name");
	while ($row = $connector->fetchArray($settings)){
		$this->Settings[$row['paramID']] = $row[$this->PlantName]; 
		//echo $row['paramID']." : ".$this->Settings[$row['paramID']]." <br>";
		}
	}
	function render($string){
	$value = eval("return $string;");
	echo $string." = ".$value."<br/>";
	}
		function represent($number,$dig = 2){
	return number_format($number,$dig,",","");
	}
}
?>
