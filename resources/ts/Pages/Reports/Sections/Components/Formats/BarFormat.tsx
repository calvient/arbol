import {Box} from '@calvient/decal';
import {
  Bar,
  BarChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
  CartesianGrid,
  Legend,
} from 'recharts';
import {stringToColor} from '../../../../../Utils/stringToColor';

interface BarChartFormatProps {
  data: Array<{name: string; value: number}>;
}

const formatNumber = (value: number) => {
  return value.toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
};

const BarFormat = ({data}: BarChartFormatProps) => {
  const keys = Object.keys(data[0]).filter((key) => key !== 'name');

  return (
    <Box mt={4} w={'full'} h={'400px'}>
      <ResponsiveContainer width='100%' height='100%'>
        <BarChart width={400} height={400} data={data}>
          {keys.length > 1 && <Legend />}
          <CartesianGrid strokeDasharray='3 3' />
          <XAxis dataKey='name' />
          <YAxis tickFormatter={formatNumber} />
          <Tooltip formatter={(value: number) => formatNumber(value)} />
          {keys.map((key) => (
            <Bar dataKey={key} fill={stringToColor(key)} />
          ))}
        </BarChart>
      </ResponsiveContainer>
    </Box>
  );
};

export default BarFormat;
