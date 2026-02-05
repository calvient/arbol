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
  isPercentage?: boolean;
}

const formatNumber = (value: number, isPercentage?: boolean) => {
  const formatted = value.toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
  return isPercentage ? `${formatted}%` : formatted;
};

const BarFormat = ({data, isPercentage}: BarChartFormatProps) => {
  const keys = Object.keys(data[0]).filter((key) => key !== 'name');

  return (
    <Box mt={4} w={'full'} h={'400px'}>
      <ResponsiveContainer width='100%' height='100%'>
        <BarChart width={400} height={400} data={data}>
          {keys.length > 1 && <Legend />}
          <CartesianGrid strokeDasharray='3 3' />
          <XAxis dataKey='name' />
          <YAxis
            domain={isPercentage ? [0, 100] : undefined}
            tickFormatter={(value: number) => formatNumber(value, isPercentage)}
          />
          <Tooltip formatter={(value: number) => formatNumber(value, isPercentage)} />
          {keys.map((key) => (
            <Bar dataKey={key} fill={stringToColor(key)} />
          ))}
        </BarChart>
      </ResponsiveContainer>
    </Box>
  );
};

export default BarFormat;
