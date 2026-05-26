import numpy as np
import pandas as pd
from deep_translator import GoogleTranslator   
from tqdm import tqdm
import re
tqdm.pandas()

icd = r"D:\downloads\doantotnghiep\data\ICD10.xlsx"
icd = pd.read_excel(icd)

translator = GoogleTranslator(source='en', target='vi')

def safe_translate(x):
    try:
        if x == 'nan' or x.strip() == '':
            return x
        return translator.translate(x)
    except:
        return x

icd['description_vi'] = icd['description'].astype(str).progress_apply(safe_translate)
icd.to_excel(r"D:\downloads\doantotnghiep\data\ICD10_vi.xlsx", index=False)

print(icd.head(5))